<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class MT_Addresses
 *
 * Manages marketplace and vendor nexus addresses.
 */
class MT_Addresses {

    /**
     * Constructor.
     *
     * Registers action hooks and filters.
     */
    public function __construct() {
        add_action( 'init', array( $this, 'init' ) );
    }

    /**
     * Initializes the class on init if taxes are enabled.
     */
    public function init() {
        if ( 'yes' !== MT()->settings->get( 'enabled' ) ) {
            return;
        }

        if ( 'vendor' === MT()->settings->get( 'merchant_of_record' ) ) {
            add_action( 'all_admin_notices', array( $this, 'admin_vendor_address_notice' ) );
            add_action( 'template_redirect', array( $this, 'vendor_address_notice' ) );
            add_action( 'wp_ajax_mt_dismiss_address_notice', array( $this, 'ajax_dismiss_vendor_address_notice' ) );

            // Hooks for clearing the mt_vendors_with_no_address transient
            $transient_hooks = apply_filters( 'mt_vendor_settings_hooks', [] );

            foreach ( $transient_hooks as $hook ) {
                add_action( $hook, array( $this, 'delete_address_transient' ) );
            }
        } else {
            add_action( 'all_admin_notices', array( $this, 'marketplace_address_notice' ) );
        }

        add_action( 'pre_get_users', array( $this, 'filter_users_query' ) );
    }

    /**
     * Gets the nexus addresses for a vendor or the marketplace.
     *
     * @param int  $vendor_id Use `0` to get the marketplace addresses.
     * @param bool $valid     Should only valid addresses be returned?
     *
     * @return array
     */
    public function get( $vendor_id = 0, $valid = false ) {
        $addresses = array_merge( $this->get_default( $vendor_id ), $this->get_additional( $vendor_id ) );

        if ( $valid ) {
            $addresses = array_filter( $addresses, array( __CLASS__, 'is_address_valid' ) );
        }

        return $addresses;
    }

    /**
     * Gets the default nexus addresses for a vendor or the marketplace.
     *
     * @param int $vendor_id Use `0` to get the marketplace addresses.
     *
     * @return array
     */
    public function get_default( $vendor_id ) {
        if ( MT_Vendors::MARKETPLACE === $vendor_id ) {
            $addresses = apply_filters(
                'mt_default_base_addresses',
                [
                    [
                        'description' => __( 'Inherited from your general shop settings', 'marketplace-taxes' ),
                        'country'     => WC()->countries->get_base_country(),
                        'postcode'    => WC()->countries->get_base_postcode(),
                        'state'       => WC()->countries->get_base_state(),
                        'city'        => WC()->countries->get_base_city(),
                        'address_1'   => WC()->countries->get_base_address(),
                    ],
                ]
            );
        } else {
            // Defer to marketplace plugin integrations
            $addresses = apply_filters( 'mt_default_vendor_addresses', [], $vendor_id );
        }

        return array_map( array( $this, 'format_default_address' ), $addresses );
    }

    /**
     * Gets any additional nexus address for a vendor or the marketplace.
     *
     * @param int $vendor_id Use `0` to get the marketplace addresses.
     *
     * @return array
     */
    private function get_additional( $vendor_id ) {
        if ( MT_Vendors::MARKETPLACE === $vendor_id ) {
            $addresses = MT()->settings->get( 'nexus_addresses' );

            if ( ! is_array( $addresses ) ) {
                $addresses = [];
            }

            $addresses = apply_filters( 'mt_additional_base_addresses', $addresses );
        } else {
            $addresses = get_user_meta( $vendor_id, 'mt_nexus_addresses', true );

            if ( ! is_array( $addresses ) ) {
                $addresses = [];
            }

            $addresses = apply_filters( 'mt_additional_vendor_addresses', $addresses, $vendor_id );
        }

        return array_map( array( $this, 'format_additional_address' ), $addresses );
    }

    /**
     * Formats a default address.
     *
     * @param array $address
     *
     * @return array Address with `default` flag and `description` set.
     */
    private function format_default_address( $address ) {
        if ( ! isset( $address['description'] ) ) {
            $address['description'] = __( 'Default address', 'marketplace-taxes' );
        }

        $address['default'] = true;

        return $address;
    }

    /**
     * Formats an additional address.
     *
     * @param array $address
     *
     * @return array Address with `default` flag unset.
     */
    private function format_additional_address( $address ) {
        $address['default'] = false;

        return $address;
    }

    /**
     * Displays an admin notice when one or more vendors have no addresses and
     * the vendor is the merchant of record.
     */
    public function admin_vendor_address_notice() {
        $notice = '';
        $class  = '';

        if ( current_user_can( 'manage_woocommerce' ) ) {
            $vendors = $this->get_vendors_with_no_address();

            if ( ! empty( $vendors ) && ! isset( $_GET['has_business_address'] ) ) {
                $notice = $this->get_vendor_address_warning( $vendors );
                $class  = 'is-dismissible';

                // Enqueue script to power up dismiss button
                MT()->assets->enqueue( 'script', 'marketplace-taxes.admin-notices' );
            }
        } else {
            $notice = $this->get_vendor_address_notice( 'admin' );
        }

        if ( ! empty( $notice ) ) {
            printf( '<div id="address_notice" class="notice notice-error %s"><p>%s</p></div>', $class, $notice );
        }
    }

    /**
     * Returns the names of all vendors with no configured nexus addresses.
     *
     * @return array
     */
    private function get_vendors_with_no_address() {
        if ( ! ( $vendors = get_transient( 'mt_vendors_with_no_address' ) ) ) {
            $query = apply_filters(
                'mt_vendor_address_query',
                [
                    'role__in'   => MT_Vendors::get_vendor_roles(),
                    'fields'     => 'ID',
                    'meta_query' => [
                        'relation' => 'AND',
                        [
                            'relation' => 'OR',
                            [
                                'key'     => 'mt_nexus_addresses',
                                'compare' => 'NOT EXISTS',
                            ],
                            [
                                'key'   => 'mt_nexus_addresses',
                                'value' => 'a:0:{}',
                            ],
                        ],
                    ],
                    'exclude'    => (array) get_option( 'mt_dismissed_vendors' ),
                ]
            );

            $vendors = get_users( $query );

            /**
             * Allows developers to filter the list of vendors with no address.
             *
             * @param array $vendors Vendor IDs returned by get_users().
             */
            $vendors = apply_filters( 'mt_vendors_with_no_address', $vendors );

            set_transient( 'mt_vendors_with_no_address', $vendors, DAY_IN_SECONDS / 2 );
        }

        return $vendors;
    }

    /**
     * Deletes the `mt_vendors_with_no_address` transient when a vendor saves
     * their settings.
     */
    public function delete_address_transient() {
        delete_transient( 'mt_vendors_with_no_address' );
    }

    /**
     * Returns the warning to display to the admin when one or more vendors have
     * no nexus address.
     *
     * @param array $vendors IDs of vendors with no address
     *
     * @return string
     */
    private function get_vendor_address_warning( $vendors ) {
        $max_vendors = 3;

        if ( sizeof( $vendors ) > $max_vendors ) {
            $num_remaining = sizeof( array_splice( $vendors, $max_vendors ) );
            $vendor_names  = array_map( array( 'MT_Vendors', 'get_store_name' ), $vendors );
            $vendor_names  = sprintf(
                __( '%1$s and <a href="%4$s">%2$s other %3$s</a>', 'marketplace-taxes' ),
                implode( ', ', $vendor_names ),
                $num_remaining,
                _n( 'seller', 'sellers', $num_remaining, 'marketplace-taxes' ),
                add_query_arg( 'has_business_address', 'false', admin_url( 'users.php' ) )
            );
        } else {
            $vendor_names = array_map( array( 'MT_Vendors', 'get_store_name' ), $vendors );
            $last_vendor  = array_pop( $vendor_names );
            $vendor_names = implode( ', ', $vendor_names );

            if ( ! empty( $vendor_names ) ) {
                $vendor_names .= ' and ' . $last_vendor;
            } else {
                $vendor_names = $last_vendor;
            }
        }

        $warning = sprintf(
            __(
                '<strong>Warning!</strong> %s %s not configured their business addresses. Taxes will not be calculated for %s.',
                'marketplace-taxes'
            ),
            $vendor_names,
            _n( 'has', 'have', sizeof( $vendors ), 'marketplace-taxes' ),
            _n( 'this vendor', 'these vendors', sizeof( $vendors ), 'marketplace-taxes' )
        );

        return $warning;
    }

    /**
     * Displays a notice to vendors on the frontend when they don't have a nexus
     * address.
     */
    public function vendor_address_notice() {
        if ( apply_filters( 'mt_should_display_vendor_notice', false ) ) {
            $notice = $this->get_vendor_address_notice( 'frontend' );

            if ( ! empty( $notice ) ) {
                wc_add_notice( $notice, 'notice' );
            }
        }
    }

    /**
     * Gets the notice to display to the authenticated vendor (if any).
     *
     * @param string $context 'admin' or 'frontend'
     *
     * @return string
     */
    private function get_vendor_address_notice( $context ) {
        $user_id = get_current_user_id();

        if ( ! MT_Vendors::is_vendor( $user_id ) ) {
            return '';
        }

        $setup_steps = mt_get_seller_setup_steps( $context );

        if ( ! mt_is_seller_setup_complete() ) {
            return wc_get_template_html(
                'address-notice.php',
                compact( 'setup_steps' ),
                'marketplace-taxes/',
                MT()->path( 'templates/' )
            );
        }

        return '';
    }

    /**
     * Displays a warning in WP admin when the marketplace is the seller of
     * record and no nexus addresses have been entered.
     */
    public function marketplace_address_notice() {
        $notice = '';

        if ( current_user_can( 'manage_woocommerce' ) ) {
            $addresses = $this->get( MT_Vendors::MARKETPLACE, true );

            if ( empty( $addresses ) ) {
                $notice = sprintf(
                    __(
                        '<strong>Tax setup incomplete.</strong> Please <a href="%s">complete your store address</a> to dismiss this notice.',
                        'marketplace-taxes'
                    ),
                    add_query_arg( 'page', 'wc-settings', admin_url( 'admin.php' ) )
                );
            }
        }

        if ( ! empty( $notice ) ) {
            printf( '<div class="notice notice-error"><p>%s</p></div>', $notice );
        }
    }

    /**
     * Checks whether the given nexus address is valid.
     *
     * @param array $address
     *
     * @return bool
     */
    public static function is_address_valid( $address ) {
        $required = [ 'country', 'postcode', 'state' ];

        foreach ( $required as $field ) {
            if ( empty( $address[ $field ] ) ) {
                return false;
            }
        }

        $states = WC()->countries->get_states( $address['country'] );

        if ( $states ) {
            return isset( $states[ $address['state'] ] );
        }

        return true;
    }

    /**
     * Filters the query used to populate the WP > Users screen.
     *
     * Shows vendors who have or don't have business addresses based on the
     * value of the `has_business_address` query parameter.
     *
     * @param WP_User_Query &$query
     */
    public function filter_users_query( $query ) {
        if ( ! isset( $_GET['has_business_address'] ) ) {
            return;
        }

        $no_address_users = (array) get_transient( 'mt_vendors_with_no_address' );

        if ( 'true' === $_GET['has_business_address'] ) {
            $query->set( 'exclude', $no_address_users );
        } else {
            if ( empty( $no_address_users ) ) {
                // All vendors have addresses - include a bogus user ID so no
                // users are matched
                $no_address_users[] = 0;
            }
            $query->set( 'include', $no_address_users );
        }

        $query->set( 'role__in', MT_Vendors::get_vendor_roles() );
    }

    /**
     * Dismisses the vendor address warning via AJAX.
     */
    public function ajax_dismiss_vendor_address_notice() {
        $dismissed_vendors = (array) get_option( 'mt_dismissed_vendors' );
        $to_dismiss        = (array) get_transient( 'mt_vendors_with_no_address' );

        update_option( 'mt_dismissed_vendors', array_merge( $dismissed_vendors, $to_dismiss ) );

        // Delete the transient so that the list of vendors with no address is
        // updated on the next page load
        $this->delete_address_transient();

        wp_send_json_success();
    }

}
