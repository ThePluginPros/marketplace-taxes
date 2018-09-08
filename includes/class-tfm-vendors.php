<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class TFM_Vendors
 *
 * Provides a generic way to retrieve information about vendors.
 */
class TFM_Vendors {

    /**
     * Special vendor ID used for the marketplace.
     */
    const MARKETPLACE = 0;

    /**
     * Gets all vendor user roles.
     *
     * @return array
     */
    public static function get_vendor_roles() {
        return TFM()->integration->get_vendor_roles();
    }

    /**
     * Checks whether a user is a vendor.
     *
     * @param int $user_id
     *
     * @return bool
     */
    public static function is_vendor( $user_id ) {
        return TFM()->integration->is_vendor( $user_id );
    }

    /**
     * Gets the name of a user's vendor store.
     *
     * @param int $user_id
     *
     * @return string
     */
    public static function get_store_name( $user_id ) {
        return TFM()->integration->get_vendor_shop_name( $user_id );
    }

}
