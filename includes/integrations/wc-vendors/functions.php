<?php

/**
 * WC Vendors integration functions.
 */

/**
 * Returns the IDs of all WC Vendors dashboard pages.
 *
 * @return array
 */
function tfm_wcv_get_dashboard_page_ids() {
    return array_merge(
        [
            get_option( 'wcvendors_vendor_dashboard_page_id' ),
            get_option( 'wcvendors_shop_settings_page_id' ),
        ],
        (array) get_option( 'wcvendors_dashboard_page_id' )
    );
}

/**
 * Checks whether a page is a WC Vendors Pro dashboard page.
 *
 * @param int $page_id Optional page ID. Defaults to current page ID.
 *
 * @return bool
 */
function tfm_wcv_is_dashboard_page( $page_id = 0 ) {
    if ( ! $page_id ) {
        $page_id = get_the_ID();
    }

    $dashboard_page_ids = (array) get_option( 'wcvendors_dashboard_page_id', [] );

    return in_array( $page_id, $dashboard_page_ids );
}
