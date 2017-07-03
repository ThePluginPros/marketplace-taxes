<?php
/**
 * WC_Report_Taxes_By_Code
 *
 * @author      WooThemes
 * @category    Admin
 * @package     WooCommerce/Admin/Reports
 * @version     2.1.0
 */
class WC_Report_Vendor_Taxes_By_Code extends WC_Admin_Report {

	/**
	 * Get the legend for the main chart sidebar
	 * @return array
	 */
	public function get_chart_legend() {
		return array();
	}

	/**
	 * Output an export link
	 */
	public function get_export_button() {

		$current_range = ! empty( $_GET['range'] ) ? sanitize_text_field( $_GET['range'] ) : 'last_month';
		?>
		<a
			href="#"
			download="report-<?php echo esc_attr( $current_range ); ?>-<?php echo date_i18n( 'Y-m-d', current_time('timestamp') ); ?>.csv"
			class="export_csv"
			data-export="table"
		>
			<?php _e( 'Export CSV', 'woocommerce' ); ?>
		</a>
		<?php
	}

	/**
	 * Output the report
	 */
	public function output_report() {

		$ranges = array(
			'year'         => __( 'Year', 'woocommerce' ),
			'last_month'   => __( 'Last Month', 'woocommerce' ),
			'month'        => __( 'This Month', 'woocommerce' ),
		);

		$current_range = ! empty( $_GET['range'] ) ? sanitize_text_field( $_GET['range'] ) : 'last_month';

		if ( ! in_array( $current_range, array( 'custom', 'year', 'last_month', 'month', '7day' ) ) ) {
			$current_range = 'last_month';
		}

		$this->calculate_current_range( $current_range );

		$hide_sidebar = true;

		include( WC()->plugin_path() . '/includes/admin/views/html-report-by-date.php');
	}

	/**
	 * Get the main chart
	 *
	 * @return string
	 */
	public function get_main_chart() {
		global $wpdb, $user_ID;

		$vendor = ign_get_user_vendor();
		
		$vendor_state = get_vendor_tax_state();
		$vendor_zip   = get_vendor_tax_zip();

		$range = self::get_report_range( isset( $_GET['range'] ) ? $_GET['range'] : '' );

		$args = array(
			'post_type' => 'vendor_commission',
			'post_status' => array( 'publish', 'private' ),
			'posts_per_page' => -1,
			'meta_query' => array(
				array(
					'key' => '_commission_vendor',
					'value' => $vendor->ID,
					'compare' => '='
				),
				array(
					'key' => '_paid_status',
					'value' => 'paid',
					'compare' => '=',
				),
				'relation' => 'AND',
			),
			'date_query' => array(
				'after' => array(
					'year'  => date('Y', $range['start_date']),
					'month' => date('n', $range['start_date']),
					'day'   => date('j', $range['start_date']),
				),
				'before' => array(
					'year'  => date('Y', $range['end_date']),
					'month' => date('n', $range['end_date']),
					'day'   => date('j', $range['end_date']),
				),
				'relation'  => 'AND',
				'inclusive' => true,
			),
		);

		$commissions = get_posts( $args );

		// Merge tax rows
		$tax_rows = array();

		//echo "<pre>";

		foreach( $commissions as $post ) {
			$order = new WC_Order( get_post_meta( $post->ID, '_order_id', true ) );

			$tracking_info = $order->ign_vendor_tracking_info;
			$vendor_taxes  = $order->vendor_taxes;

			if ( !isset( $vendor_taxes[ $user_ID ] ) )
				continue;

			$full_commission     = get_post_meta( $post->ID, '_commission_amount', true );
			$total_tax           = get_post_meta( $post->ID, '_tax', true );
			$total_tax           = $total_tax ? wc_round_tax_total( $total_tax ) : 0;
			$item_commission     = get_post_meta( $post->ID, '_amount', true );
			$shipping_commission = get_post_meta( $post->ID, '_shipping', true );

			// We need to adjust the tax amount if a refund (partial or full) has occurred
			if ( $order->get_status() == 'refunded' ) {
				continue;
			} else if ( isset( $tracking_info[ $vendor->ID ] ) && $tracking_info[ $vendor->ID ]['status'] == 'Refunded' ) {
				$refund_amount = isset( $tracking_info[ $vendor->ID ]['refund_amount'] ) ? $tracking_info[ $vendor->ID ]['refund_amount'] : $full_commission - $total_tax;

				if ( $refund_amount >= $item_commission ) {
					$taxable_item_price = 0;
					$refund_amount -= $item_commission;
				} else {
					$taxable_item_price = $item_commission - $refund_amount;
					$refund_amount = 0;
				}

				if ( $refund_amount >= $shipping_commission ) {
					$taxable_shipping_price = 0;
				} else {
					$taxable_shipping_price = $shipping_commission - $refund_amount;
				}

				// Recalculate tax due with new taxable item/shipping amounts
				$user_id = isset( $vendor->admins ) ? $vendor->admins[0]->ID : -1;

				if ( $user_id != -1 ) {
					$tax_state = get_vendor_tax_state( $user_id );
					$tax_zip   = get_vendor_tax_zip( $user_id );

					if ( get_state_type( $tax_state ) == 'orig' ) {
						// Calculate tax based on vendor location
						$location = array(
							'country'  => 'US',
							'state'    => $tax_state,
							'city'     => '',
							'postcode' => $tax_zip,
						);
					} else {
						$tax_based_on = get_option( 'woocommerce_tax_based_on' );

						if ( 'base' === $tax_based_on ) {
							$default  = wc_get_base_location();
							$country  = $default['country'];
							$state    = $default['state'];
							$postcode = '';
							$city     = '';
						} elseif ( 'billing' === $tax_based_on ) {
							$country  = $order->billing_country;
							$state    = $order->billing_state;
							$postcode = $order->billing_postcode;
							$city     = $order->billing_city;
						} else {
							$country  = $order->shipping_country;
							$state    = $order->shipping_state;
							$postcode = $order->shipping_postcode;
							$city     = $order->shipping_city;
						}

						$location = array(
							'country'  => $country,
							'state'    => $state,
							'postcode' => $postcode,
							'city'     => $city,
						);
					}

					$rates = WC_Tax::find_rates( $location );

					$item_taxes     = WC_Tax::calc_tax( $taxable_item_price, $rates );
					$shipping_taxes = WC_Tax::calc_shipping_tax( $taxable_shipping_price, $rates );

					foreach ( array_keys( $item_taxes + $shipping_taxes ) as $_tax_id ) {
						$tax_amount          = isset( $item_taxes[ $_tax_id ] ) ? wc_round_tax_total( $item_taxes[ $_tax_id ] ) : 0;
						$shipping_tax_amount = isset( $shipping_taxes[ $_tax_id ] ) ? wc_round_tax_total( $shipping_taxes[ $_tax_id ] ) : 0;

						if ( $tax_amount + $shipping_tax_amount == 0 )
							continue;

						$tax_rows[ $_tax_id ] = isset( $tax_rows[ $_tax_id ] ) ? $tax_rows[ $_tax_id ] : (object) array( 'tax_amount' => 0, 'shipping_tax_amount' => 0, 'total_orders' => 0 );
					
						$tax_rows[ $_tax_id ]->total_orders += 1;
						$tax_rows[ $_tax_id ]->tax_rate = WC_Tax::get_rate_code( $_tax_id );

						//echo "Commission #". $post->ID .": ";

						$tax_rows[ $_tax_id ]->tax_amount += $tax_amount;
						//echo "Tax: ". wc_round_tax_total( $tax_amount ) .", ";

						$tax_rows[ $_tax_id ]->shipping_tax_amount += $shipping_tax_amount;
						//echo "Shipping Tax: ". wc_round_tax_total( $shipping_tax_amount );

						//echo "\r\n";
					}
				}
			} else if ( is_array( $vendor_taxes[ $user_ID ]['cart'] ) && is_array( $vendor_taxes[ $user_ID ]['shipping'] ) ) {
				$merged_rates = $vendor_taxes[ $user_ID ]['cart'] + $vendor_taxes[ $user_ID ]['shipping'];

				foreach ( array_keys( $merged_rates ) as $_tax_id ) {
					$tax_amount          = isset( $vendor_taxes[ $user_ID ]['cart'][ $_tax_id ] ) ? wc_round_tax_total( $vendor_taxes[ $user_ID ]['cart'][ $_tax_id ] ) : 0;
					$shipping_tax_amount = isset( $vendor_taxes[ $user_ID ]['shipping'][ $_tax_id ] ) ? wc_round_tax_total( $vendor_taxes[ $user_ID ]['shipping'][ $_tax_id ] ) : 0;

					if ( $tax_amount + $shipping_tax_amount == 0 )
						continue;

					$tax_rows[ $_tax_id ] = isset( $tax_rows[ $_tax_id ] ) ? $tax_rows[ $_tax_id ] : (object) array( 'tax_amount' => 0, 'shipping_tax_amount' => 0, 'total_orders' => 0 );
				
					$tax_rows[ $_tax_id ]->total_orders += 1;
					$tax_rows[ $_tax_id ]->tax_rate = WC_Tax::get_rate_code( $_tax_id );

					//echo "Commission #". $post->ID .": ";

					$tax_rows[ $_tax_id ]->tax_amount += wc_round_tax_total( $vendor_taxes[ $user_ID ]['cart'][ $_tax_id ] );
					//echo "Tax: ". wc_round_tax_total( $tax_amount ) .", ";

					$tax_rows[ $_tax_id ]->shipping_tax_amount += wc_round_tax_total( $vendor_taxes[ $user_ID ]['shipping'][ $_tax_id ] );
					//echo "Shipping Tax: ". wc_round_tax_total( $shipping_tax_amount );

					//echo "\r\n";
				}
			}
		}

		//echo "</pre>";

		?>
		<table class="widefat">
			<thead>
				<tr>
					<th><?php _e( 'Tax', 'woocommerce' ); ?></th>
					<th><?php _e( 'Rate', 'woocommerce' ); ?></th>
					<th class="total_row"><?php _e( 'Number of Orders', 'woocommerce' ); ?></th>
					<th class="total_row"><?php _e( 'Tax Amount', 'woocommerce' ); ?> <a class="tips" data-tip="<?php esc_attr_e( 'This is the sum of the "Tax Rows" tax amount within your orders.', 'woocommerce' ); ?>" href="#">[?]</a></th>
					<th class="total_row"><?php _e( 'Shipping Tax Amount', 'woocommerce' ); ?> <a class="tips" data-tip="<?php esc_attr_e( 'This is the sum of the "Tax Rows" shipping tax amount within your orders.', 'woocommerce' ); ?>" href="#">[?]</a></th>
					<th class="total_row"><?php _e( 'Total Tax', 'woocommerce' ); ?> <a class="tips" data-tip="<?php esc_attr_e( 'This is the total tax for the rate (shipping tax + product tax).', 'woocommerce' ); ?>" href="#">[?]</a></th>
				</tr>
			</thead>
			<?php if ( $tax_rows ) : ?>
				<tbody>
					<?php
					foreach ( $tax_rows as $rate_id => $tax_row ) {
						$rate = $wpdb->get_var( $wpdb->prepare( "SELECT tax_rate FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_id = %d;", $rate_id ) );
						?>
						<tr>
							<th scope="row"><?php echo apply_filters( 'woocommerce_reports_taxes_tax_rate', $tax_row->tax_rate, $rate_id, $tax_row ); ?></th>
							<td><?php echo apply_filters( 'woocommerce_reports_taxes_rate', $rate, $rate_id, $tax_row ); ?>%</td>
							<td class="total_row"><?php echo $tax_row->total_orders; ?></td>
							<td class="total_row"><?php echo wc_price( $tax_row->tax_amount ); ?></td>
							<td class="total_row"><?php echo wc_price( $tax_row->shipping_tax_amount ); ?></td>
							<td class="total_row"><?php echo wc_price( $tax_row->tax_amount + $tax_row->shipping_tax_amount ); ?></td>
						</tr>
						<?php
					}
					?>
				</tbody>
				<tfoot>
					<tr>
						<th scope="row" colspan="3"><?php _e( 'Total', 'woocommerce' ); ?></th>
						<th class="total_row"><?php echo wc_price( wc_round_tax_total( array_sum( wp_list_pluck( (array) $tax_rows, 'tax_amount' ) ) ) ); ?></th>
						<th class="total_row"><?php echo wc_price( wc_round_tax_total( array_sum( wp_list_pluck( (array) $tax_rows, 'shipping_tax_amount' ) ) ) ); ?></th>
						<th class="total_row"><strong><?php echo wc_price( wc_round_tax_total( array_sum( wp_list_pluck( (array) $tax_rows, 'tax_amount' ) ) + array_sum( wp_list_pluck( (array) $tax_rows, 'shipping_tax_amount' ) ) ) ); ?></strong></th>
					</tr>
				</tfoot>
			<?php else : ?>
				<tbody>
					<tr>
						<td><?php _e( 'No taxes found in this period', 'woocommerce' ); ?></td>
					</tr>
				</tbody>
			<?php endif; ?>
		</table>
		<?php
	}

	// Get start/end date for report
	private static function get_report_range( $current_range = null ) {
		if ( !$current_range )
			$current_range = 'last_month'; // Show taxes for last month by default
	
		$start_date = '';
		$end_date   = '';

		switch ( $current_range ) {
			case 'custom' :
				$start_date = strtotime( sanitize_text_field( $_GET['start_date'] ) );
				$end_date   = strtotime( 'midnight', strtotime( sanitize_text_field( $_GET['end_date'] ) ) );

				if ( ! $end_date ) {
					$end_date = current_time('timestamp');
				}
			break;

			case 'year' :
				$start_date = strtotime( date( 'Y-01-01', current_time('timestamp') ) );
				$end_date   = strtotime( 'midnight', current_time( 'timestamp' ) );
			break;

			case 'last_month' :
				$first_day_current_month = strtotime( date( 'Y-m-01', current_time( 'timestamp' ) ) );
				$start_date = strtotime( date( 'Y-m-01', strtotime( '-1 DAY', $first_day_current_month ) ) );
				$end_date   = strtotime( date( 'Y-m-t', strtotime( '-1 DAY', $first_day_current_month ) ) );
			break;

			case 'month' :
				$start_date = strtotime( date( 'Y-m-01', current_time('timestamp') ) );
				$end_date   = strtotime( 'midnight', current_time( 'timestamp' ) );
			break;
		}

		return array( 
			'start_date' => $start_date, 
			'end_date'   => $end_date 
		);
	}
}
