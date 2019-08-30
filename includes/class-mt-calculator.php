<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once 'class-mt-cart-proxy.php';

/**
 * Calculator.
 *
 * Uses the TaxJar SmartCalcs API to calculate the tax due for an order.
 *
 * @package Marketplace_Taxes
 */
class MT_Calculator {

    /**
     * @var int Unique tax rate ID for the current order
     */
    private $tax_rate_id = 0;

    /**
     * @var MT_Cart_Proxy Current cart (when calculating cart totals)
     */
    private $cart;

    /**
     * Constructor.
     *
     * Registers action hooks and filters.
     */
    public function __construct() {
        add_action( 'init', array( $this, 'init' ) );
    }

    /**
     * Initializes the calculator if tax calculations are enabled.
     */
    public function init() {
        if ( 'yes' === MT()->settings->get( 'enabled', 'yes' ) ) {
            $this->hooks();
        }
    }

    /**
     * Registers required action hooks and filters.
     */
    private function hooks() {
        add_filter( 'woocommerce_cart_shipping_packages', array( $this, 'split_cart_shipping_packages' ), 20 );
        add_filter( 'woocommerce_shipping_package_name', array( $this, 'rename_vendor_shipping_package' ), 10, 3 );
        add_action( 'woocommerce_calculate_totals', array( $this, 'calculate_tax_for_cart' ) );
        add_filter( 'woocommerce_calculated_total', array( $this, 'add_tax_to_cart_total' ) );
        add_action( 'wp_ajax_woocommerce_calc_line_taxes', array( $this, 'calculate_tax_for_order' ), 5 );
        add_filter( 'woocommerce_rate_code', array( $this, 'override_rate_code' ), 10, 2 );
    }

    /**
     * Splits the cart shipping packages by vendor to ease tax calculations.
     *
     * Ripped from the WC Vendors Pro code.
     *
     * @param array $packages
     *
     * @return array
     */
    public function split_cart_shipping_packages( $packages ) {
        // Bail if the cart packages have already been split by vendor
        $packages_already_split = false;

        foreach ( $packages as $package ) {
            if ( $this->get_package_vendor_id( $package ) ) {
                $packages_already_split = true;
                break;
            }
        }

        if ( apply_filters( 'mt_cart_packages_split', $packages_already_split, $packages ) ) {
            return $packages;
        }

        // Reset the packages
        $packages     = [];
        $vendor_items = [];

        foreach ( WC()->cart->get_cart() as $item_key => $item ) {
            $post = get_post( $item['product_id'] );

            if ( $item['data']->needs_shipping() ) {
                $vendor_items[ $post->post_author ][ $item_key ] = $item;
            }
        }

        foreach ( $vendor_items as $vendor_id => $items ) {
            $contents_cost = array_sum( wp_list_pluck( $items, 'line_total' ) );

            $packages[] = [
                'contents'        => $items,
                'contents_cost'   => $contents_cost,
                'applied_coupons' => WC()->cart->applied_coupons,
                'vendor_id'       => $vendor_id,
                'created_by'      => 'marketplace-taxes',
                'destination'     => [
                    'country'   => WC()->customer->get_shipping_country(),
                    'state'     => WC()->customer->get_shipping_state(),
                    'postcode'  => WC()->customer->get_shipping_postcode(),
                    'city'      => WC()->customer->get_shipping_city(),
                    'address'   => WC()->customer->get_shipping_address(),
                    'address_2' => WC()->customer->get_shipping_address_2(),
                ],
            ];
        }

        return $packages;
    }

    /**
     * Renames the shipping packages based on the vendor sold by.
     *
     * @param string $title   The shipping package title
     * @param int    $count   The shipping package position
     * @param array  $package The package from the cart
     *
     * @return string $title The modified shipping package title
     */
    public function rename_vendor_shipping_package( $title, $count, $package ) {
        if ( isset( $package['created_by'] ) && 'marketplace-taxes' === $package['created_by'] ) {
            $vendor_sold_by = MT()->integration->get_vendor_sold_by( $package['vendor_id'] );
            $title          = sprintf( __( '%s Shipping', 'marketplace-taxes' ), $vendor_sold_by );
            $title          = apply_filters(
                'mt_vendor_shipping_package_title',
                $title,
                $count,
                $package,
                $vendor_sold_by
            );
        }

        return $title;
    }

    /**
     * Calculates the tax for a given cart.
     *
     * @param WC_Cart $cart
     */
    public function calculate_tax_for_cart( $cart ) {
        $cart_tax     = 0;
        $shipping_tax = 0;
        $cart_keys    = [];

        foreach ( $cart->get_cart() as $cart_item_key => $item ) {
            $cart_keys[ $item['data']->get_id() ] = $cart_item_key;
        }

        $tax_orders = $this->get_tax_orders_from_cart( $cart );

        if ( ! $this->tax_rate_id ) {
            $this->tax_rate_id = $this->create_rate_id( $tax_orders );
        }

        $this->cart = new MT_Cart_Proxy( $cart, $this->tax_rate_id );

        foreach ( $tax_orders as $order ) {
            try {
                $result = $this->calculate( $order );

                foreach ( $result['line_items'] as $line_item ) {
                    if ( isset( $cart_keys[ $line_item['id'] ] ) ) {
                        $this->cart->set_cart_item_tax( $cart_keys[ $line_item['id'] ], $line_item['tax'] );
                        $cart_tax += $line_item['tax'];
                    }
                }

                foreach ( $result['shipping_lines'] as $shipping_line ) {
                    $this->cart->set_package_tax( $shipping_line['id'], $shipping_line['tax'] );
                    $shipping_tax += $shipping_line['tax'];
                }
            } catch ( Exception $ex ) {
                $message = sprintf(
                    __( 'Failed to calculate the tax due: %s', 'marketplace-taxes' ),
                    $ex->getMessage()
                );

                wc_get_logger()->warning( $message );
            }
        }

        $this->cart->set_tax_amount( $this->tax_rate_id, $cart_tax );
        $this->cart->set_shipping_tax_amount( $this->tax_rate_id, $shipping_tax );
        $this->cart->update_tax_totals();
    }

    /**
     * Gets the tax orders from a cart.
     *
     * @param WC_Cart $cart
     *
     * @return array Orders to send to TaxJar for tax calculations.
     */
    public function get_tax_orders_from_cart( $cart ) {
        $orders = [];

        if ( 'marketplace' === MT()->settings->get( 'merchant_of_record' ) ) {
            $order = [
                'from_address'   => $this->get_base_address(),
                'line_items'     => array_map( array( $this, 'format_cart_item' ), $cart->get_cart() ),
                'shipping_lines' => [],
            ];

            foreach ( $cart->get_shipping_packages() as $key => $package ) {
                $order['shipping_lines'][] = [
                    'id'    => $key,
                    'total' => $this->get_package_shipping_cost( $key ),
                ];
            }

            if ( $this->is_local_pickup_cart() ) {
                $order['to_address'] = $this->get_base_address();
            } else {
                $order['to_address'] = [
                    'country'  => WC()->customer->get_shipping_country(),
                    'postcode' => WC()->customer->get_shipping_postcode(),
                    'state'    => WC()->customer->get_shipping_state(),
                    'city'     => WC()->customer->get_shipping_city(),
                    'address'  => WC()->customer->get_shipping_address(),
                ];
            }

            $orders[] = $order;
        } else {
            foreach ( $this->get_vendor_cart_packages( $cart ) as $vendor_id => $package ) {
                $order = [
                    'vendor_id'    => $vendor_id,
                    'from_address' => $this->get_vendor_from_address( $vendor_id ),
                    'line_items'   => array_map( array( $this, 'format_cart_item' ), $package['contents'] ),
                    'to_address'   => [
                        'country'  => $package['destination']['country'],
                        'postcode' => $package['destination']['postcode'],
                        'state'    => $package['destination']['state'],
                        'city'     => $package['destination']['city'],
                        'address'  => isset( $package['destination']['address'] ) ? $package['destination']['address'] : $package['destination']['address_1'],
                    ],
                ];

                if ( isset( $package['shipping'] ) ) {
                    $order['shipping_lines'] = [
                        [
                            'id'    => $package['shipping']['key'],
                            'total' => $package['shipping']['cost'],
                        ],
                    ];
                }

                $orders[] = $order;
            }
        }

        return apply_filters( 'mt_cart_tax_orders', $orders, $cart );
    }

    /**
     * Formats a cart item as a TaxJar line item.
     *
     * @param array $cart_item
     *
     * @return array
     */
    private function format_cart_item( $cart_item ) {
        $product   = $cart_item['data'];
        $line_item = [
            'id'               => $product->get_id(),
            'quantity'         => $cart_item['quantity'],
            'product_tax_code' => MT_Util::get_product_tax_code( $product->get_id() ),
            'unit_price'       => round(
                $cart_item['line_total'] / $cart_item['quantity'],
                wc_get_price_decimals()
            ),
            'discount'         => $cart_item['line_subtotal'] - $cart_item['line_total'],
        ];

        return $line_item;
    }

    /**
     * Gets the vendor shipping packages from the cart.
     *
     * One package is returned for each vendor. Unlike normal WC shipping
     * packages, these packages contain virtual products that don't need
     * shipping.
     *
     * @param WC_Cart $cart
     *
     * @return array
     */
    private function get_vendor_cart_packages( $cart ) {
        $virtual_items   = $this->get_virtual_cart_items( $cart );
        $vendor_packages = [];

        foreach ( $cart->get_shipping_packages() as $key => $package ) {
            $vendor_id = $this->get_package_vendor_id( $package );

            if ( empty( $vendor_id ) ) {
                continue;
            }

            // Add virtual items as needed
            if ( isset( $virtual_items[ $vendor_id ] ) ) {
                $items_to_add = array_diff_key( $package['contents'], $virtual_items );

                foreach ( $items_to_add as $key => $item ) {
                    $package['contents'][ $key ] = $item;
                    $package['contents_cost']    += $item['line_total'];
                }

                unset( $virtual_items[ $vendor_id ] );
            }

            // Set destination address correctly
            $package['destination'] = $this->get_package_destination( $key, $package );

            // Set shipping cost
            $package['shipping'] = [
                'key'  => $key,
                'cost' => $this->get_package_shipping_cost( $key ),
            ];

            $vendor_packages[ $vendor_id ] = $package;
        }

        // Edge case: vendor only has virtual items
        foreach ( $virtual_items as $vendor_id => $items ) {
            $vendor_packages[ $vendor_id ] = [
                'contents'      => $items,
                'contents_cost' => array_sum( wp_list_pluck( $items, 'line_total' ) ),
                'vendor_id'     => $vendor_id,
                'destination'   => [
                    'country'  => WC()->customer->get_billing_country(),
                    'postcode' => WC()->customer->get_billing_postcode(),
                    'city'     => WC()->customer->get_billing_city(),
                    'state'    => WC()->customer->get_billing_state(),
                    'address'  => WC()->customer->get_billing_address(),
                ],
            ];
        }

        return apply_filters( 'mt_vendor_cart_packages', $vendor_packages, $cart );
    }

    /**
     * Gets the virtual items from a cart grouped by vendor ID.
     *
     * @param WC_Cart $cart
     *
     * @return array
     */
    private function get_virtual_cart_items( $cart ) {
        $vendor_items = [];

        foreach ( $cart->get_cart() as $cart_key => $item ) {
            $product = $item['data'];

            if ( ! $product->needs_shipping() ) {
                $vendor_id = MT()->integration->get_vendor_from_product( $product->get_id() );

                if ( ! isset( $vendor_items[ $vendor_id ] ) ) {
                    $vendor_items[ $vendor_id ] = [];
                }

                $vendor_items[ $vendor_id ][ $cart_key ] = $item;
            }
        }

        return $vendor_items;
    }

    /**
     * Gets the vendor ID from a WooCommerce shipping package.
     *
     * Dokan uses the key `seller_id` for the vendor ID whereas WC Vendors Pro uses `vendor_id`.
     *
     * @param array $package
     *
     * @return int
     */
    private function get_package_vendor_id( $package ) {
        if ( isset( $package['vendor_id'] ) ) {
            return $package['vendor_id'];
        } else if ( isset( $package['seller_id'] ) ) {
            return $package['seller_id'];
        }

        return 0;
    }

    /**
     * Gets the correct shipping destination for a package.
     *
     * @param int   $key Package key
     * @param array $package
     *
     * @return array Package destination address
     */
    private function get_package_destination( $key, $package ) {
        $shipping_method = $this->get_package_shipping_method( $key );

        if ( true === apply_filters( 'woocommerce_apply_base_tax_for_local_pickup', true ) && in_array(
                $shipping_method,
                apply_filters( 'woocommerce_local_pickup_methods', [ 'local_pickup', 'legacy_local_pickup' ] )
            ) ) {
            return $this->get_base_address();
        } elseif ( isset( $package['destination'] ) ) {
            return $package['destination'];
        } else {
            return [
                'country'  => WC()->customer->get_shipping_country(),
                'postcode' => WC()->customer->get_shipping_postcode(),
                'city'     => WC()->customer->get_shipping_city(),
                'state'    => WC()->customer->get_shipping_state(),
                'address'  => WC()->customer->get_shipping_address(),
            ];
        }
    }

    /**
     * Returns the selected shipping method for a package.
     *
     * @param int $key
     *
     * @return string Method ID
     */
    private function get_package_shipping_method( $key ) {
        $chosen_methods = WC()->session->get( 'chosen_shipping_methods' );

        if ( isset( $chosen_methods[ $key ] ) ) {
            return $chosen_methods[ $key ];
        }

        return '';
    }

    /**
     * Gets a vendor's from address.
     *
     * @param int $vendor_id
     *
     * @return array
     */
    private function get_vendor_from_address( $vendor_id ) {
        $from_address = MT()->integration->get_vendor_from_address( $vendor_id );

        return apply_filters( 'mt_vendor_from_address', $from_address, $vendor_id );
    }

    /**
     * Gets the calculated shipping cost for a shipping package.
     *
     * @param int $key
     *
     * @return float
     */
    private function get_package_shipping_cost( $key ) {
        $shipping_packages = WC()->shipping()->get_packages();

        if ( ! isset( $shipping_packages[ $key ] ) ) {
            return 0;
        }

        $package       = $shipping_packages[ $key ];
        $chosen_method = $this->get_package_shipping_method( $key );

        if ( ! isset( $package['rates'], $package['rates'][ $chosen_method ] ) ) {
            return 0;
        }

        return $package['rates'][ $chosen_method ]->cost;
    }

    /**
     * Determines whether the main cart is for local pickup.
     */
    private function is_local_pickup_cart() {
        if ( true !== apply_filters( 'woocommerce_apply_base_tax_for_local_pickup', true ) ) {
            return false;
        }

        return sizeof(
                   array_intersect(
                       wc_get_chosen_shipping_method_ids(),
                       apply_filters(
                           'woocommerce_local_pickup_methods',
                           array( 'legacy_local_pickup', 'local_pickup' )
                       )
                   )
               ) > 0;
    }

    /**
     * Calculates the tax for a given order.
     *
     * Overrides WC_AJAX::calc_line_taxes()
     */
    public function calculate_tax_for_order() {
        check_ajax_referer( 'calc-totals', 'security' );

        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_die( -1 );
        }

        $order_id = absint( $_POST['order_id'] );

        // Parse the jQuery serialized items
        $items = array();
        parse_str( $_POST['items'], $items );

        // Save order items first
        wc_save_order_items( $order_id, $items );

        // Grab the order and recalc taxes
        $order      = wc_get_order( $order_id );
        $tax_orders = $this->get_tax_orders_from_order( $order );

        if ( ! $this->tax_rate_id ) {
            $this->tax_rate_id = $this->create_rate_id( $tax_orders );
        }

        foreach ( $tax_orders as $tax_order ) {
            try {
                $response = $this->calculate( $tax_order );

                $this->set_line_item_taxes( $response['line_items'] );
                $this->set_shipping_taxes( $response['shipping_lines'] );
            } catch ( Exception $ex ) {
                wc_get_logger()->warning(
                    sprintf(
                        'Failed to calculate tax for order #%s: %s',
                        $order->get_order_number(),
                        $ex->getMessage()
                    )
                );
            }
        }

        $order = wc_get_order( $order_id );
        $order->update_taxes();
        $order->calculate_totals( false );

        // Return HTML items
        include WC()->plugin_path() . '/includes/admin/meta-boxes/views/html-order-items.php';
        wp_die();
    }

    /**
     * Sets the taxes for a set of order line items.
     *
     * @param array $line_items Line items from API response
     */
    private function set_line_item_taxes( $line_items ) {
        foreach ( $line_items as $line_item ) {
            $item  = new WC_Order_Item_Product( $line_item['id'] );
            $taxes = $item->get_taxes();

            if ( ! is_array( $taxes['total'] ) ) {
                $taxes['total'] = [];
            }
            if ( ! is_array( $taxes['subtotal'] ) ) {
                $taxes['subtotal'] = [];
            }

            $taxes['total'][ $this->tax_rate_id ]    = $line_item['tax'];
            $taxes['subtotal'][ $this->tax_rate_id ] = $line_item['tax'];

            $item->set_taxes( $taxes );
            $item->save();
        }
    }

    /**
     * Sets the taxes for a set of order shipping methods.
     *
     * @param array $shipping_lines Shipping line items from API response
     *
     * @throws WC_Data_Exception May throw exception if tax amount is invalid
     */
    private function set_shipping_taxes( $shipping_lines ) {
        foreach ( $shipping_lines as $shipping_line ) {
            $item  = new WC_Order_Item_Shipping( $shipping_line['id'] );
            $taxes = $item->get_taxes();

            if ( ! isset( $taxes['total'] ) ) {
                $taxes['total'] = [];
            }
            $taxes['total'][ $this->tax_rate_id ] = $shipping_line['tax'];

            $item->set_taxes( $taxes );
            $item->save();
        }
    }

    /**
     * Gets the tax orders for a WooCommerce shop order.
     *
     * @todo create a separate tax order for each vendor
     *
     * @param WC_Order $order
     *
     * @return array
     */
    private function get_tax_orders_from_order( $order ) {
        $tax_order = [
            'from_address'   => $this->get_base_address(),
            'line_items'     => [],
            'shipping_lines' => [],
        ];

        if ( $this->is_local_pickup_order( $order ) ) {
            $tax_order['to_address'] = $this->get_base_address();
        } else {
            $tax_order['to_address'] = [
                'country'  => $order->get_shipping_country(),
                'postcode' => $order->get_shipping_postcode(),
                'state'    => $order->get_shipping_state(),
                'city'     => $order->get_shipping_city(),
                'address'  => $order->get_shipping_address_1(),
            ];
        }

        foreach ( $order->get_items() as $item_id => $item ) {
            $tax_order['line_items'][] = [
                'id'               => $item_id,
                'quantity'         => $item->get_quantity(),
                'product_tax_code' => MT_Util::get_product_tax_code( $item->get_product()->get_id() ),
                'unit_price'       => round(
                    $item->get_total() / $item->get_quantity(),
                    wc_get_price_decimals()
                ),
                'discount'         => $item->get_subtotal() - $item->get_total(),
            ];
        }

        foreach ( $order->get_shipping_methods() as $item_id => $shipping_method ) {
            $tax_order['shipping_lines'][] = [
                'id'    => $item_id,
                'total' => $shipping_method->get_total(),
            ];
        }

        return apply_filters( 'mt_order_tax_orders', [ $tax_order ], $order );
    }

    /**
     * Checks whether an order is for local pickup.
     *
     * @param WC_Order $order
     *
     * @return bool True if the order contains at least one local pickup method
     */
    private function is_local_pickup_order( $order ) {
        $shipping_methods = $order->get_items( 'shipping' );

        if ( empty( $shipping_methods ) ) {
            return false;
        }

        foreach ( $shipping_methods as $shipping_method ) {
            if ( in_array(
                $shipping_method->get_method_id(),
                apply_filters( 'woocommerce_local_pickup_methods', array( 'legacy_local_pickup', 'local_pickup' ) )
            ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gets the store base address.
     *
     * @return array
     */
    private function get_base_address() {
        return [
            'country'  => WC()->countries->get_base_country(),
            'postcode' => WC()->countries->get_base_postcode(),
            'state'    => WC()->countries->get_base_state(),
            'city'     => WC()->countries->get_base_city(),
            'address'  => WC()->countries->get_base_address(),
        ];
    }

    /**
     * Checks whether a destination address is valid.
     *
     * @param array $destination
     *
     * @return bool
     */
    private function is_valid_destination( $destination ) {
        $defaults = [
            'to_country' => '',
            'to_state'   => '',
            'to_city'    => '',
            'to_street'  => '',
        ];

        $destination = wp_parse_args( $destination, $defaults );

        if ( empty( $destination['to_country'] ) ) {
            return false;
        }

        $country = $destination['to_country'];

        if ( 'US' === $country && empty( $destination['to_zip'] ) ) {
            return false;
        } elseif ( in_array( $country, [ 'CA', 'US' ] ) && empty( $destination['to_state'] ) ) {
            return false;
        }

        return true;
    }

    /**
     * Creates a tax rate ID for this order.
     *
     * @param array $orders Orders to be sent to TaxJar
     *
     * @return string
     */
    private function create_rate_id( $orders ) {
        return (string) hexdec( substr( md5( json_encode( $orders ) ), 0, 15 ) );
    }

    /**
     * Sets the tax rate code for the TFM tax rate.
     *
     * @param string $code
     * @param mixed  $tax_rate_id
     *
     * @return string
     */
    public function override_rate_code( $code, $tax_rate_id ) {
        if ( $this->tax_rate_id === (string) $tax_rate_id ) {
            $code = 'TFM-TAX-RATE-1';
        }

        return $code;
    }

    /**
     * Calculates the tax or an order with the TaxJar SmartCalcs API.
     *
     * @param array $order
     *
     * @return array
     *
     * @throws Exception If tax calculation fails
     */
    private function calculate( $order ) {
        $order = wp_parse_args(
            $order,
            [
                'line_items'     => [],
                'shipping_lines' => [],
                'from_address'   => [],
                'to_address'     => [],
                'vendor_id'      => MT_Vendors::MARKETPLACE,
            ]
        );

        if ( empty( $order['line_items'] ) ) {
            throw new Exception( 'At least one line item is required.' );
        }

        $from_address = $this->format_address( $order['from_address'], 'from' );
        $to_address   = $this->format_address( $order['to_address'], 'to' );

        if ( ! $this->is_valid_destination( $to_address ) ) {
            throw new Exception( 'Destination address is invalid.' );
        }

        $transient_key = 'mt_rates_' . md5( json_encode( $order ) );

        if ( ! ( $response = get_transient( $transient_key ) ) ) {
            $nexus_addresses = $this->get_nexus_addresses( $order['vendor_id'] );

            if ( empty( $nexus_addresses ) ) {
                throw new Exception( 'At least one nexus address is required.' );
            }

            $shipping_lines = $order['shipping_lines'];
            $order          = array_merge(
                $from_address,
                $to_address,
                [
                    'nexus_addresses' => $nexus_addresses,
                    'shipping'        => array_sum(
                        array_map( 'abs', wp_list_pluck( $order['shipping_lines'], 'total' ) )
                    ),
                    'line_items'      => array_values( $order['line_items'] ),
                ]
            );

            try {
                $tax = MT()->client()->taxForOrder( $order );

                if ( isset( $tax->breakdown ) ) {
                    $response = $this->prepare_response( $tax->breakdown, $shipping_lines );
                } else {
                    $response = [
                        'line_items'     => [],
                        'shipping_lines' => [],
                    ];
                }
            } catch ( Exception $ex ) {
                throw new Exception( 'Error from TaxJar was ' . $ex->getMessage(), 0, $ex );
            }

            set_transient( $transient_key, $response, DAY_IN_SECONDS * 14 );
        }

        return $response;
    }

    /**
     * Formats an address for TaxJar.
     *
     * @param array  $address
     * @param string $type 'from' or 'to'
     *
     * @return array
     */
    private function format_address( $address, $type ) {
        $address_key_map = [
            'postcode' => 'zip',
            'address'  => 'street',
        ];

        $new_address = [];

        foreach ( $address as $key => $value ) {
            if ( isset( $address_key_map[ $key ] ) ) {
                $key = $address_key_map[ $key ];
            }
            $new_address[ $type . '_' . $key ] = $value;
        }

        return $new_address;
    }

    /**
     * Gets the nexus addresses for the specified vendor formatted for TaxJar.
     *
     * @param int $vendor_id
     *
     * @return array
     */
    private function get_nexus_addresses( $vendor_id ) {
        $addresses = [];

        foreach ( MT()->addresses->get( $vendor_id ) as $index => $address ) {
            $addresses[] = [
                'id'      => $vendor_id . '_' . $index,
                'country' => $address['country'],
                'zip'     => $address['postcode'],
                'state'   => $address['state'],
                'city'    => $address['city'],
                'street'  => $address['address_1'],
            ];
        }

        return $addresses;
    }

    /**
     * Prepares a taxForOrder response.
     *
     * @param object $breakdown Tax breakdown from TaxJar SmartCalcs
     * @param array  $shipping_items
     *
     * @return array Line items and shipping lines with tax amounts
     */
    private function prepare_response( $breakdown, $shipping_items ) {
        $line_items = [];

        foreach ( $breakdown->line_items as $line_item ) {
            $line_items[] = [
                'id'  => $line_item->id,
                'tax' => $line_item->tax_collectable,
            ];
        }

        $shipping_lines = [];

        if ( isset( $breakdown->shipping ) ) {
            $shipping_tax   = $breakdown->shipping->tax_collectable;
            $total_shipping = array_sum( wp_list_pluck( $shipping_items, 'total' ) );

            // Distribute shipping tax proportionally amongst items
            foreach ( $shipping_items as $shipping_item ) {
                $tax_amount = 0.0;

                if ( 0 < $total_shipping ) {
                    $tax_amount = ( $shipping_item['total'] / $total_shipping ) * $shipping_tax;
                }

                $shipping_lines[] = [
                    'id'  => $shipping_item['id'],
                    'tax' => round( $tax_amount, wc_get_price_decimals() ),
                ];
            }
        }

        return compact( 'line_items', 'shipping_lines' );
    }

    /**
     * Adds the calculated tax to the cart total (WC 3.2+)
     *
     * @param float $total Total calculated by WooCommerce (excl. tax)
     *
     * @return float
     */
    public function add_tax_to_cart_total( $total ) {
        if ( version_compare( WC_VERSION, '3.2', '>=' ) ) {
            $total += $this->cart->get_cart_contents_tax();
            $total += $this->cart->get_fee_tax();
            $total += $this->cart->get_shipping_tax();
        }

        return $total;
    }

}

new MT_Calculator();
