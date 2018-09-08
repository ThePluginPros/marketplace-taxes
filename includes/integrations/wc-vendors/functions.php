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
