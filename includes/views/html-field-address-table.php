<?php

/**
 * Nexus Addresses table template.
 *
 * @global string $context   'admin' or 'frontend'
 * @global array  $value     Selected addresses
 * @global array  $countries Countries available for selection
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// A different field key is used on the admin settings page
if ( ! isset( $field_key ) ) {
    $field_key = 'nexus_addresses';
}

$in_admin = 'admin' === $context;

// Breakpoint below which the table switches to a mobile friendly layout
$mobile_breakpoint = apply_filters( 'mt_mobile_breakpoint', $in_admin ? 1200 : 992, $in_admin );

?>

<?php do_action( 'mt_before_nexus_addresses_table', $context ); ?>

<table id="nexus_addresses_table" class="<?php echo $in_admin ? 'widefat' : ''; ?>">
    <thead>
    <tr>
        <th><?php _e( 'Address', 'marketplace-taxes' ); ?></th>
        <th><?php _e( 'Country', 'marketplace-taxes' ); ?> <span class="required">*</span></th>
        <th><?php _e( 'State', 'marketplace-taxes' ); ?> <span class="required">*</span></th>
        <th><?php _e( 'City', 'marketplace-taxes' ); ?></th>
        <th><?php _e( 'Postcode', 'marketplace-taxes' ); ?> <span class="required">*</span></th>
        <th class="actions">&nbsp;</th>
    </tr>
    </thead>
    <tfoot>
    <tr>
        <th colspan="6">
            <button type="button"
                    class="vt-add-nexus-address <?php echo 'admin' === $context ? 'wp-core-ui button' : ''; ?>">
                <?php _e( 'Add Address', 'marketplace-taxes' ); ?>
            </button>
        </th>
    </tr>
    </tfoot>
    <tbody id="nexus_addresses">
    <!-- Placeholder -->
    </tbody>
</table>

<?php do_action( 'mt_after_nexus_addresses_table', $context ); ?>

<script type="text/html" id="tmpl-vt-nexus-addresses-empty">
    <tr id="nexus_addresses_blank_row">
        <td colspan="6">
            <p>
                <?php printf(
                    '%s <a href="#" class="vt-add-nexus-address">%s</a>',
                    __( 'No addresses entered.', 'marketplace-taxes' ),
                    __( 'Add one.', 'marketplace-taxes' )
                ); ?>
            </p>
        </td>
    </tr>
</script>

<script type="text/html" id="tmpl-vt-nexus-address">
    <tr data-id="{{ data.id }}">
        <td>
            <input type="text" name="<?php echo $field_key; ?>[{{ data.id }}][address_1]"
                   id="<?php echo $field_key; ?>[{{ data.id }}][address_1]"
                   placeholder="<?php esc_attr_e( 'Street address', 'marketplace-taxes' ); ?>"
                   value="{{ data.address_1 }}">
        </td>
        <td>
            <select name="<?php echo $field_key; ?>[{{ data.id }}][country]"
                    id="<?php echo $field_key; ?>[{{ data.id }}][country]"
                    class="mt_country_to_state" required>
                <?php foreach ( $countries as $code => $name ): ?>
                    <option value="<?php echo $code; ?>"><?php echo $name; ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <input type="text" name="<?php echo $field_key; ?>[{{ data.id }}][state]"
                   id="<?php echo $field_key; ?>[{{ data.id }}][state]"
                   class="shipping_state" placeholder="<?php esc_attr_e( 'State', 'marketplace-taxes' ); ?>"
                   value="{{ data.state }}" required>
        </td>
        <td>
            <input type="text" name="<?php echo $field_key; ?>[{{ data.id }}][city]"
                   id="<?php echo $field_key; ?>[{{ data.id }}][city]"
                   placeholder="<?php esc_attr_e( 'City', 'marketplace-taxes' ); ?>" value="{{ data.city }}">
        </td>
        <td>
            <input type="text" name="<?php echo $field_key; ?>[{{ data.id }}][postcode]"
                   id="<?php echo $field_key; ?>[{{ data.id }}][postcode]"
                   placeholder="<?php esc_attr_e( 'Postcode', 'marketplace-taxes' ); ?>"
                   value="{{ data.postcode }}" required>
        </td>
        <td class="actions">
            <a href="#" class="vt-remove-nexus-address" title="Remove">
                <i class="dashicons dashicons-no-alt"></i>
            </a>
        </td>
    </tr>
</script>

<script type="text/html" id="tmpl-vt-nexus-address-default">
    <tr data-id="{{ data.id }}" class="default">
        <td>
            <span class="address-field">{{ data.address_1 ? data.address_1 : '–' }}</span>
        </td>
        <td>
            <span class="address-field">{{ data.country ? data.country : '–' }}</span>
        </td>
        <td>
            <span class="address-field">{{ data.state ? data.state : '–' }}</span>
        </td>
        <td>
            <span class="address-field">{{ data.city ? data.city : '–' }}</span>
        </td>
        <td>
            <span class="address-field">{{ data.postcode ? data.postcode : '–' }}</span>
        </td>
        <td class="actions">
            <span class="default-address-tip" title="{{ data.description }}">
                <i class="dashicons dashicons-editor-help"></i>
            </span>
        </td>
    </tr>
</script>

<style>
    @media screen and (max-width: <?php echo $mobile_breakpoint; ?>px) {
        #nexus_addresses_table thead {
            display: none;
        }

        #nexus_addresses_table tr {
            display: block;
            position: relative;
            padding: 1.2em 0;
        }

        #nexus_addresses_table.widefat tr {
            padding: 1em;
        }

        #nexus_addresses_table tfoot tr {
            padding-bottom: 0;
        }

        #nexus_addresses_table tfoot tr th {
            display: table-row;
        }

        #nexus_addresses_table tbody tr:first-of-type {
            border-top: 1px solid #ccc;
        }

        #nexus_addresses_table.widefat tbody tr:first-of-type {
            border-top: none;
        }

        #nexus_addresses_table td {
            display: table-row;
        }

        #nexus_addresses_table td:before {
            content: attr(data-label);
            display: table-cell;
            font-weight: bold;
            padding: 0.2em 0.6em 0.2em 0;
            text-align: right;
        }

        #nexus_addresses_table td:last-child:after {
            content: "";
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            border-bottom: 1px solid #ccc;
        }

        #nexus_addresses_table input, #nexus_addresses_table select {
            margin-bottom: 0.8em;
        }

        #nexus_addresses_table tr td:nth-child(5) input {
            margin-bottom: 0;
        }

        span.default-address-tip:before, a.vt-remove-nexus-address:before {
            content: attr(title);
            font-size: small;
            font-style: italic;
        }

        span.default-address-tip i, a.vt-remove-nexus-address i {
            display: none;
        }

        .widefat span.default-address-tip, .widefat a.vt-remove-nexus-address {
            display: block;
            margin-top: 0.5em;
        }
    }
</style>

<?php
MT()->assets->enqueue(
    'script',
    'marketplace-taxes.address-table',
    [
        'deps'     => [
            'jquery',
            'wp-util',
            'underscore',
            'backbone',
            'marketplace-taxes.country-select',
        ],
        'localize' => [
            'wcv_tax_address_table_localize' => [
                'addresses' => is_array( $value ) ? $value : [],
                'strings'   => [
                    'locations_error' => __(
                        'At least one business address is required.',
                        'marketplace-taxes'
                    ),
                ],
            ],
        ],
    ]
);
?>
