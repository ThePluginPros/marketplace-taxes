<?php

/**
 * Nexus Addresses table template.
 *
 * @global string $context 'admin' or 'frontend'
 * @global array $value Selected addresses
 * @global array $countries Countries available for selection
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

?>

<?php do_action( 'tfm_before_nexus_addresses_table', $context ); ?>

<table id="nexus_addresses_table" class="<?php echo 'admin' === $context ? 'widefat' : ''; ?>">
    <thead>
    <tr>
        <th><?php _e( 'Address 1', 'taxjar-for-marketplaces' ); ?></th>
        <th><?php _e( 'Address 2', 'taxjar-for-marketplaces' ); ?></th>
        <th><?php _e( 'Country', 'taxjar-for-marketplaces' ); ?></th>
        <th><?php _e( 'State', 'taxjar-for-marketplaces' ); ?></th>
        <th><?php _e( 'City', 'taxjar-for-marketplaces' ); ?></th>
        <th><?php _e( 'Postcode', 'taxjar-for-marketplaces' ); ?></th>
        <th width="30"><!-- Actions --></th>
    </tr>
    </thead>
    <tfoot>
    <tr>
        <th colspan="7">
            <button type="button"
                    class="vt-add-nexus-address <?php echo 'admin' === $context ? 'wp-core-ui button' : ''; ?>">
                <?php _e( 'Add Address', 'taxjar-for-marketplaces' ); ?>
            </button>
        </th>
    </tr>
    </tfoot>
    <tbody id="nexus_addresses">
    <!-- Placeholder -->
    </tbody>
</table>

<?php do_action( 'tfm_after_nexus_addresses_table', $context ); ?>

<script type="text/html" id="tmpl-vt-nexus-addresses-empty">
    <tr id="nexus_addresses_blank_row">
        <td colspan="7">
            <p><?php printf(
                    '%s <a href="#" class="vt-add-nexus-address">%s</a>',
                    __( 'No addresses entered.', 'taxjar-for-marketplaces' ),
                    __( 'Add one.', 'taxjar-for-marketplaces' )
                ); ?></p>
        </td>
    </tr>
</script>

<script type="text/html" id="tmpl-vt-nexus-address">
    <tr data-id="{{ data.id }}">
        <td>
            <input type="text" name="nexus_addresses[{{ data.id }}][address_1]"
                   id="nexus_addresses[{{ data.id }}][address_1]"
                   placeholder="<?php esc_attr_e( 'Street address', 'taxjar-for-marketplaces' ); ?>"
                   value="{{ data.address_1 }}">
        </td>
        <td>
            <input type="text" name="nexus_addresses[{{ data.id }}][address_2]"
                   id="nexus_addresses[{{ data.id }}][address_2]"
                   placeholder="<?php esc_attr_e( 'Apartment, suite, etc.', 'taxjar-for-marketplaces' ); ?>"
                   value="{{ data.address_2 }}">
        </td>
        <td>
            <select name="nexus_addresses[{{ data.id }}][country]" id="nexus_addresses[{{ data.id }}][country]"
                    class="tfm_country_to_state">
                <?php foreach ( $countries as $code => $name ): ?>
                    <option value="<?php echo $code; ?>"><?php echo $name; ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <input type="text" name="nexus_addresses[{{ data.id }}][state]" id="nexus_addresses[{{ data.id }}][state]"
                   class="shipping_state" placeholder="<?php esc_attr_e( 'State', 'taxjar-for-marketplaces' ); ?>"
                   value="{{ data.state }}">
        </td>
        <td>
            <input type="text" name="nexus_addresses[{{ data.id }}][city]" id="nexus_addresses[{{ data.id }}][city]"
                   placeholder="<?php esc_attr_e( 'City', 'taxjar-for-marketplaces' ); ?>" value="{{ data.city }}">
        </td>
        <td>
            <input type="text" name="nexus_addresses[{{ data.id }}][postcode]"
                   id="nexus_addresses[{{ data.id }}][postcode]"
                   placeholder="<?php esc_attr_e( 'Postcode', 'taxjar-for-marketplaces' ); ?>"
                   value="{{ data.postcode }}">
        </td>
        <td width="30">
            <a href="#" class="vt-remove-nexus-address" title="Remove">
                <i class="dashicons dashicons-no-alt"></i>
            </a>
        </td>
    </tr>
</script>

<?php
TFM()->assets->enqueue(
    'script',
    'taxjar-for-marketplaces.address-table',
    [
        'deps'     => [ 'jquery', 'wp-util', 'underscore', 'backbone', 'taxjar-for-marketplaces.country-select' ],
        'localize' => [
            'wcv_tax_address_table_localize' => [
                'addresses' => is_array( $value ) ? $value : [],
                'strings'   => [
                    'locations_error' => __(
                        'At least one business address is required.',
                        'taxjar-for-marketplaces'
                    ),
                ],
            ],
        ],
    ]
);
?>
