<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Tax settings template.
 */

$collect_tax = WCV_Taxes_Util::does_vendor_collect_tax();
?>

<div class="tabs-content hide-all" id="tax">
	<div class="wcv-cols-group wcv-horizontal-gutters">
		<div class="all-100">
			<div class="control-group">
				<ul class="control unstyled inline" style="margin: 0; padding: 0">
					<li>
						<input type="checkbox" name="collect_tax" value="1" <?php checked( $collect_tax ); ?> />
						<label for="collect_tax" style="cursor: default;">Collect Sales Tax</label>
					</li>
				</ul>
				<p class="tip">Use this setting to enable or disable taxes for your store.</p>
			</div>
		</div>
	</div>
</div>