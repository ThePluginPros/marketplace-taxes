<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Calculation method.
 *
 * Base class extended by tax calculation methods.
 *
 * @author  Brett Porcelli
 * @package WCV_Taxes
 * @since   0.0.1
 */
abstract class WCV_Taxes_Calculation_Method {

    /**
     * @var string Method ID.
     */
    protected $id = '';

    /**
     * @var string Name.
     */
    protected $name = '';

    /**
     * @var bool Enabled?
     */
    protected $enabled = false;

    /**
     * @var string Description.
     */
    protected $description = '';

    /**
     * @var string Admin Description.
     */
    protected $admin_description = '';

    /**
     * @var array Options.
     */
    protected $options = array();

    /**
     * Constructor.
     *
     * @since 0.0.1
     */
    public function __construct() {
        $this->enabled = $this->get_option( 'enabled' );
    }
    
    /**
     * Getter for ID.
     *
     * @since 0.0.1
     *
     * @return string
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Getter for name.
     *
     * @since 0.0.1
     *
     * @return string
     */
    public function get_name() {
        return $this->name;
    }

    /**
     * Getter for description.
     *
     * @since 0.0.1
     *
     * @return string
     */
    public function get_description() {
        return $this->description;
    }

    /**
     * Getter for admin description.
     *
     * @since 0.0.1
     *
     * @return string
     */
    public function get_admin_description() {
        return $this->admin_description;
    }

    /**
     * Getter for options.
     *
     * @since 0.0.1
     *
     * @return array
     */
    public function get_options() {
        return $this->options;
    }

    /**
     * Get an option.
     *
     * @since 0.0.1
     *
     * @param  string $option_name
     * @return mixed
     */
    public function get_option( $option_name ) {
        $default = false;

        if ( isset( $this->options[ $option_name ], $this->options[ $option_name ]['std'] ) ) {
            $default = $this->options[ $option_name ]['std'];
        }

        $option_name = $this->id . '_' . $option_name;

        if ( isset( WC_Vendors::$pv_options ) ) {
            return WC_Vendors::$pv_options->get_option( $option_name );
        } else {
            $options = get_option( 'wc_prd_vendor_options', array() );

            if ( isset( $options[ $option_name ] ) ) {
                return $options[ $option_name ];
            } else {
                return $default;
            }
        }
    }

    /**
     * Getter for admin options.
     *
     * @since 0.0.1
     *
     * @return array
     */
    public function get_admin_options() {
        $admin_options = array();

        foreach ( $this->options as $option ) {
            if ( isset( $option['admin'] ) && $option['admin'] ) {
                $admin_options[] = $option;
            }
        }

        return $admin_options;
    }

    /**
     * Is the method enabled?
     *
     * @since 0.0.1
     *
     * @return bool
     */
    public function is_enabled() {
        return $this->enabled;
    }

    /**
     * Calculate the sales tax for a given package.
     *
     * @since 0.0.1
     *
     * @param  array $package
     * @return array
     */
    abstract public function calculate_taxes( $package );

    /**
     * Optional callback executed when a new order is created. Can be used to
     * save session data after checkout.
     *
     * @since 0.0.1
     *
     * @param int $order_id
     */
    public function order_created( $order_id ) { }

    /**
     * Optional callback executed when an order is shipped.
     *
     * @since 0.0.1
     *
     * @param int $order_id
     */
    public function order_shipped( $order_id ) { }

    /**
     * Optional callback executed when an order is partially or fully refunded.
     *
     * @since 0.0.1
     *
     * @param int $refund_id
     * @param int $order_id
     */
    public function order_refunded( $refund_id, $order_id ) { }

}