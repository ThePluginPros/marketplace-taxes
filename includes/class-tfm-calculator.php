<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Calculator.
 *
 * Uses the TaxJar SmartCalcs API to calculate the tax due for an order.
 *
 * @package TaxJar_For_Marketplaces
 */
class TFM_Calculator {

    /**
     * @const Fabricated tax code used for taxable products
     */
    const GENERAL_TAX_CODE = '00000';

    /**
     * @const Fabricated tax code used for shipping
     */
    const SHIPPING_TAX_CODE = '11010';

    /**
     * @var array Map from product tax codes to applicable tax rates
     */
    private $tax_rates = [];

    /**
     * @var int Unique tax rate ID for the current order
     */
    private $tax_rate_id = 0;

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
        if ( 'yes' === TFM()->settings->get( 'enabled', 'yes' ) ) {
            $this->hooks();
        }
    }

    /**
     * Registers required action hooks and filters.
     */
    private function hooks() {
        add_action( 'woocommerce_calculate_totals', array( $this, 'calculate_tax_for_cart' ) );
        add_action( 'woocommerce_saved_order_items', array( $this, 'calculate_tax_for_order' ), 10, 2 );
        add_filter( 'pre_option_woocommerce_shipping_tax_class', array( $this, 'override_shipping_tax_class' ) );
        add_filter( 'woocommerce_product_get_tax_class', array( $this, 'override_product_tax_class' ), 10, 2 );
        add_filter( 'woocommerce_order_item_get_tax_class', array( $this, 'override_order_item_tax_class' ), 10, 2 );
        add_filter( 'woocommerce_find_rates', array( $this, 'override_tax_rates' ), 10, 2 );
        add_filter( 'woocommerce_rate_code', array( $this, 'override_rate_code' ), 10, 2 );
    }

    /**
     * Calculates the tax for a given cart.
     *
     * @param WC_Cart $cart
     */
    public function calculate_tax_for_cart( $cart ) {
        $this->tax_rates = [];

        $line_items = [];

        foreach ( $cart->get_cart() as $cart_item ) {
            $product      = $cart_item['data'];
            $line_items[] = [
                'id'               => $product->get_id(),
                'quantity'         => $cart_item['quantity'],
                'product_tax_code' => TFM_Util::get_product_tax_code( $product->get_id() ),
                'unit_price'       => round(
                    $cart_item['line_total'] / $cart_item['quantity'],
                    wc_get_price_decimals()
                ),
                'discount'         => $cart_item['line_subtotal'] - $cart_item['line_total'],
            ];
        }

        if ( $this->is_local_pickup_cart() ) {
            $destination = $this->get_base_address();
        } else {
            $destination = [
                'to_country' => WC()->customer->get_shipping_country(),
                'to_zip'     => WC()->customer->get_shipping_postcode(),
                'to_state'   => WC()->customer->get_shipping_state(),
                'to_city'    => WC()->customer->get_shipping_city(),
                'to_street'  => WC()->customer->get_shipping_address(),
            ];
        }

        try {
            $this->tax_rates = $this->get_rates( $destination, $line_items, $cart->get_shipping_total() );
        } catch ( Exception $ex ) {
            $message = sprintf( __( 'Failed to calculate the tax due: %s', 'taxjar-for-marketplaces' ), $ex->getMessage() );

            // Display warnings at checkout if debug mode is enabled
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                wc_add_notice( $message, 'error' );
            }

            wc_get_logger()->warning( $message );
        }
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
                    apply_filters( 'woocommerce_local_pickup_methods', array( 'legacy_local_pickup', 'local_pickup' ) )
                )
            ) > 0;
    }

    /**
     * Calculates the tax for a given order.
     *
     * @param int $order_id
     */
    public function calculate_tax_for_order( $order_id ) {
        $ajax_action = isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : '';

        if ( 'woocommerce_calc_line_taxes' !== $ajax_action ) {
            return;
        }

        $this->tax_rates = [];

        $order      = wc_get_order( $order_id );
        $line_items = [];

        foreach ( $order->get_items() as $item ) {
            $product      = $item->get_product();
            $line_items[] = [
                'id'               => $product->get_id(),
                'quantity'         => $item->get_quantity(),
                'product_tax_code' => TFM_Util::get_product_tax_code( $product->get_id() ),
                'unit_price'       => round(
                    $item->get_total() / $item->get_quantity(),
                    wc_get_price_decimals()
                ),
                'discount'         => $item->get_subtotal() - $item->get_total(),
            ];
        }

        if ( $this->is_local_pickup_order( $order ) ) {
            $destination = $this->get_base_address();
        } else {
            $destination = [
                'to_country' => $order->get_shipping_country(),
                'to_zip'     => $order->get_shipping_postcode(),
                'to_state'   => $order->get_shipping_state(),
                'to_city'    => $order->get_shipping_city(),
                'to_street'  => $order->get_shipping_address_1(),
            ];
        }

        try {
            $this->tax_rates = $this->get_rates( $destination, $line_items, $order->get_shipping_total() );
        } catch ( Exception $ex ) {
            wc_get_logger()->warning(
                sprintf( 'Failed to calculate tax for order #%s: %s', $order->get_order_number(), $ex->getMessage() )
            );
        }
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
     * Gets the store base address formatted for TaxJar.
     *
     * @return array
     */
    private function get_base_address() {
        return [
            'to_country' => WC()->countries->get_base_country(),
            'to_zip'     => WC()->countries->get_base_postcode(),
            'to_state'   => WC()->countries->get_base_state(),
            'to_city'    => WC()->countries->get_base_city(),
            'to_street'  => WC()->countries->get_base_address(),
        ];
    }

    /**
     * Gets the applicable tax rates for a given order.
     *
     * @param array $destination Order destination
     * @param array $line_items Line items formatted for TaxJar
     * @param float $shipping Total shipping
     *
     * @return array Tax rates
     *
     * @throws Exception If rates can't be retrieved
     */
    private function get_rates( $destination, $line_items, $shipping ) {
        if ( ! $this->is_valid_destination( $destination ) ) {
            throw new Exception( 'Destination address is invalid.' );
        }

        if ( empty( $line_items ) ) {
            throw new Exception( 'At least one line item is required.' );
        }

        $transient_key = 'tfm_rates_' . md5( json_encode( compact( 'destination', 'line_items', 'shipping' ) ) );

        if ( ! ( $rates = get_transient( $transient_key ) ) ) {
            $nexus_addresses = [];

            foreach ( $line_items as $line_item ) {
                $vendor_id = WCV_Vendors::get_vendor_from_product( $line_item['id'] );

                if ( $vendor_id > 0 ) {
                    $vendor_addresses = get_user_meta( $vendor_id, 'tfm_nexus_addresses', true );

                    if ( is_array( $vendor_addresses ) ) {
                        foreach ( $vendor_addresses as $index => $address ) {
                            $nexus_addresses[] = [
                                'id'      => $vendor_id . '_' . $index,
                                'country' => $address['country'],
                                'zip'     => $address['postcode'],
                                'state'   => $address['state'],
                                'city'    => $address['city'],
                                'street'  => $address['address_1'],
                            ];
                        }
                    }
                }
            }

            if ( empty( $nexus_addresses ) ) {
                throw new Exception( 'At least one nexus address is required.' );
            }

            $order = array_merge( $destination, compact( 'shipping', 'nexus_addresses', 'line_items' ) );

            try {
                $tax = TFM()->client()->taxForOrder( $order );

                if ( isset( $tax->breakdown ) ) {
                    $rates          = [];
                    $item_tax_codes = wp_list_pluck( $line_items, 'product_tax_code', 'id' );

                    foreach ( $tax->breakdown->line_items as $line_item ) {
                        $tax_code           = $item_tax_codes[ $line_item->id ];
                        $rates[ $tax_code ] = $line_item->combined_tax_rate;
                    }

                    if ( 0 < $shipping && isset( $tax->breakdown->shipping ) ) {
                        $rates[ self::SHIPPING_TAX_CODE ] = $tax->breakdown->shipping->combined_tax_rate;
                    }
                }
            } catch ( Exception $ex ) {
                throw new Exception( 'Error from TaxJar was ' . $ex->getMessage(), 0, $ex );
            }

            set_transient( $transient_key, $rates, DAY_IN_SECONDS * 14 );
        }

        return $rates;
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
     * Filters the shipping tax class.
     *
     * @return string
     */
    public function override_shipping_tax_class() {
        return self::SHIPPING_TAX_CODE;
    }

    /**
     * Dynamically sets the tax class for products based on their tax category.
     *
     * @param string $tax_class
     * @param WC_Product $product
     *
     * @return string
     */
    public function override_product_tax_class( $tax_class, $product ) {
        $tax_code = TFM_Util::get_product_tax_code( $product->get_id() );

        if ( ! empty( $tax_code ) ) {
            $tax_class = $tax_code;
        }

        return $tax_class;
    }

    /**
     * Sets the correct tax class for order items.
     *
     * @param string $tax_class
     * @param WC_Order_Item $item
     *
     * @return string
     */
    public function override_order_item_tax_class( $tax_class, $item ) {
        if ( is_a( $item, 'WC_Order_Item_Product' ) ) {
            $tax_class = TFM_Util::get_product_tax_code( $item->get_product_id() );
        } elseif ( is_a( $item, 'WC_Order_Item_Shipping' ) ) {
            $tax_class = self::SHIPPING_TAX_CODE;
        }

        return $tax_class;
    }

    /**
     * Tells WooCommerce which tax rates to use based on the TaxJar API response.
     *
     * @param array $matched_rates
     * @param array $args
     *
     * @return array
     */
    public function override_tax_rates( $matched_rates, $args ) {
        $tax_class = $args['tax_class'];

        if ( isset( $this->tax_rates[ $tax_class ] ) ) {
            if ( ! $this->tax_rate_id ) {
                $this->tax_rate_id = $this->create_rate_id( $args );
            }

            $matched_rates[ $this->tax_rate_id ] = [
                'rate'     => $this->get_tax_rate( $tax_class ),
                'label'    => WC()->countries->tax_or_vat(),
                'shipping' => self::SHIPPING_TAX_CODE === $tax_class ? 'yes' : 'no',
                'compound' => 'no',
            ];
        }

        return $matched_rates;
    }

    /**
     * Creates a tax rate ID for this order.
     *
     * @param array $args Arguments passed to WC_Tax::find_rates()
     *
     * @return string
     */
    private function create_rate_id( $args ) {
        return (string) hexdec( substr( md5( implode( '_', $args ) ), 0, 15 ) );
    }

    /**
     * Gets a tax rate formatted as a percentage.
     *
     * @param string $tax_rate_id
     *
     * @return string
     */
    private function get_tax_rate( $tax_rate_id ) {
        return number_format( $this->tax_rates[ $tax_rate_id ] * 100, 4 );
    }

    /**
     * Sets the tax rate code for the TFM tax rate.
     *
     * @param string $code
     * @param mixed $tax_rate_id
     *
     * @return string
     */
    public function override_rate_code( $code, $tax_rate_id ) {
        if ( $this->tax_rate_id === (string) $tax_rate_id ) {
            $code = 'TFM-TAX-RATE-1';
        }

        return $code;
    }

}

new TFM_Calculator();
