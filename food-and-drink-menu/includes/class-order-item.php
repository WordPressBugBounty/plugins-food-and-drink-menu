<?php
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'fdmOrderItem' ) ) {
/**
 * Class for any order made through the plugin
 *
 * @since 2.1.0
 */
class fdmOrderItem {

	// The WP post object that corresponds to this order item
	public $post;

	// The ID for this order item
	public $id, $ID;

	// The date this order item was created
	public $date;

	// The items that were included in this order
	public $order_items = array();

	// The customer's name for this order
	public $name;

	// The customer's email for this order
	public $email;

	// The customer's phone for this order
	public $phone;

	// The customer's note about this order
	public $note;

	// The time this order was created
	public $order_time;

	// The status of this order
	public $post_status;

	// When the customer has requested the order be ready for.
	public $pickup_time;

	// Whether this is a delivery order
	public $delivery;
	public $fulfillment_mode = '';
	public $table_id = '';
	public $table_label = '';

	// The URL that the order was received from
	public $permalink;

	// The total cost of the order
	public $order_total;

	// The receipt id of the online order
	public $receipt_id;

	// The discount code used for this order
	public $discount_code;

	// The amount paid online for this order
	public $payment_amount;

	// The order amount before discounts, tax, tip and delivery
	public $subtotal;

	// The amount removed from the order by a discount code
	public $discount_amount;

	// The amount paid for the order to be delivered
	public $delivery_amount;

	// The amount added to the order by the customer as a tip
	public $tip_amount;

	// The amount added to the order as tax
	public $tax_amount;

	// The time the order is expected to be ready for pickup
	public $estimated_time;

	// The custom fields associated with this order
	public $custom_fields = array();
	public $custom_field_snapshots = array();

	// Stripe payment properties
	public $stripe_payment_intent_id;
	public $stripe_payment_hold_status;
	public $stripe_customer_id;
	public $edit_confirmation_code;
	public $pricing_version;
	public $order_currency;
	public $pricing_snapshot_version;
	public $currency;

	// Stores any errors found during the validation process
	public $validation_errors = array();

	public function __construct( $args = array() ) {
		
		// Parse the values passed
		$this->parse_args( $args );
	}

	public function load( $post ) {

		if ( is_int( $post ) || is_string( $post ) ) {
			$post = get_post( $post );
		}

		if ( $post instanceof WP_Post && FDM_ORDER_POST_TYPE === $post->post_type ) {
			$this->load_wp_post( $post );
			return true;
		}
		else {
			return false;
		}
	}

	public function load_wp_post( $post ) {

		// Store post for access to other data if needed by extensions
		$this->post = $post;

		$this->id = $this->ID = $post->ID;
		$this->date = $post->post_date;
		$this->order_items = unserialize( $post->post_content );
		$this->post_status = $post->post_status;

		$this->load_post_metadata();

		do_action( 'fdm_order_load_post_data', $this, $post );
	}

	public function load_post_metadata() {

		$meta_defaults = array(
			'name' => '',
			'email' => '',
			'phone' => '',
			'note' => '',
			'pickup_time' => __( 'Immediately', 'food-and-drink-menu' ),
			'delivery' => false,
			'receipt_id' => '',
			'discount_code' => '',
			'payment_amount' => 0,
			'order_total' => 0,
			'subtotal' => 0,
			'tax_amount' => 0,
			'delivery_amount' => 0,
			'tip_amount' => 0,
			'discount_amount' => 0,
			'estimated_time' => '24:00',
			'permalink' => get_site_url(),
			'custom_fields'   => array(),
			'custom_field_snapshots' => array(),
			'pricing_version' => 0,
			'order_currency'  => '',
			'pricing_snapshot_version' => 0,
			'currency' => '',
			'fulfillment_mode' => '', 'table_id' => '', 'table_label' => '',
		);

		$meta_defaults = apply_filters( 'fdm_order_metadata_defaults', $meta_defaults );

		if ( is_array( $meta = get_post_meta( $this->ID, 'order_data', true ) ) ) {
			$meta = array_merge( $meta_defaults, get_post_meta( $this->ID, 'order_data', true ) );
		} else {
			$meta = $meta_defaults;
		}

		$this->name = $meta['name'];
		$this->email = $meta['email'];
		$this->phone = $meta['phone'];
		$this->note = $meta['note'];
		$this->pickup_time = $meta['pickup_time'];
		$this->permalink = $meta['permalink'];
		$this->delivery = $meta['delivery'];
		$this->fulfillment_mode = $meta['fulfillment_mode'] ?: ( $this->delivery ? 'delivery' : 'pickup' );
		$this->table_id = $meta['table_id'];
		$this->table_label = $meta['table_label'];
		$this->receipt_id = $meta['receipt_id'];
		$this->discount_code = $meta['discount_code'];
		$this->payment_amount = $meta['payment_amount'];
		$this->order_total = $meta['order_total'];
		$this->subtotal = $meta['subtotal'];
		$this->tax_amount = $meta['tax_amount'];
		$this->delivery_amount = $meta['delivery_amount'];
		$this->tip_amount = $meta['tip_amount'];
		$this->discount_amount = $meta['discount_amount'];
		$this->estimated_time = $meta['estimated_time'];
		$this->custom_fields = $meta['custom_fields'];
		$this->custom_field_snapshots = is_array( $meta['custom_field_snapshots'] ) ? $meta['custom_field_snapshots'] : array();
		$this->pricing_version = $meta['pricing_version'] ?: $meta['pricing_snapshot_version'];
		$this->order_currency = $meta['order_currency'] ?: $meta['currency'];
		$this->pricing_snapshot_version = $meta['pricing_snapshot_version'] ?: $this->pricing_version;
		$this->currency = $meta['currency'] ?: $this->order_currency;
	}

	public function update( $args ) {

		if ( ! is_array( $args ) ) { return; }

		foreach ( $args as $key => $value ) {
			$this->$key = $value;
		}
	}

	public function format_date( $date ) {

		$date = mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $date);
		return apply_filters( 'get_the_date', $date );
	}

	public function save_order_post( $payment_transition = false ) {

		$post = array(
			'post_title'	=> $this->name,
			'post_content'	=> serialize( $this->order_items ),
			'post_type'		=> FDM_ORDER_POST_TYPE
		);

		// If updating, save post meta before updating the post
		if ( isset( $this->id ) ) { 

			$post['ID'] = $this->id;
			if ( $payment_transition ) $post['post_status'] = $this->post_status;

			$this->save_order_post_meta();

			return wp_update_post( $post, true );
		}
		// If new order, insert order as draft, save post meta, then update status
		// it is done this way, because we send notifications on post_status
		// transition hook and notificatons need post_meta information
		else {

			$post['post_status'] = 'draft';

			$post_id = wp_insert_post( $post, true );

			if ( is_wp_error( $post_id ) ) {
				return $post_id;
			}

			if ( $post_id ) { 
				
				$this->id = $post_id;
	
				$this->save_order_post_meta(); 
			}

			$args = array(
				'ID'          => $this->id,
				'post_status' => $this->post_status
			);

			return wp_update_post( $args, true );
		}
	}

	public function save_order_post_meta() {

		$postmeta = array(
			'name'            => $this->name,
			'email'           => $this->email,
			'phone'           => $this->phone,
			'note'            => $this->note,
			'pickup_time'     => $this->pickup_time,
			'delivery'        => $this->delivery,
			'fulfillment_mode' => $this->fulfillment_mode ?: ( $this->delivery ? 'delivery' : 'pickup' ),
			'table_id' => $this->table_id, 'table_label' => $this->table_label,
			'receipt_id'      => $this->receipt_id,
			'discount_code'   => $this->discount_code,
			'permalink'       => $this->permalink,
			'payment_amount'  => $this->payment_amount,
			'order_total'     => $this->order_total,
			'delivery_amount' => $this->delivery_amount,
			'subtotal' 	      => $this->subtotal,
			'tax_amount'      => $this->tax_amount,
			'tip_amount'      => $this->tip_amount,
			'discount_amount' => $this->discount_amount,
			'estimated_time'  => $this->estimated_time,
			'custom_fields'	  => $this->custom_fields,
			'custom_field_snapshots' => is_array( $this->custom_field_snapshots ) ? $this->custom_field_snapshots : array(),
			'pricing_version' => isset( $this->pricing_version ) ? (int) $this->pricing_version : 0,
			'order_currency'  => isset( $this->order_currency ) ? strtoupper( sanitize_text_field( $this->order_currency ) ) : '',
			'pricing_snapshot_version' => isset( $this->pricing_snapshot_version ) ? (int) $this->pricing_snapshot_version : ( isset( $this->pricing_version ) ? (int) $this->pricing_version : 0 ),
			'currency' => isset( $this->currency ) && $this->currency ? strtoupper( sanitize_text_field( $this->currency ) ) : ( isset( $this->order_currency ) ? strtoupper( sanitize_text_field( $this->order_currency ) ) : '' ),
		);

		$postmeta = apply_filters( 'fdm_insert_order_metadata', $postmeta, $this );

		update_post_meta( $this->id, 'order_data', $postmeta );
	}

	public function set_order_items( $items ) {

		$this->order_items = $items;
	}

	public function get_order_items() {

		return is_array( $this->order_items ) ? $this->order_items : array();
	}

	/**
	 * Validates an admin update to an order
	 * @since 2.4.1
	 */
	public function process_admin_order_update() {
		global $fdm_controller;

		$this->validate_admin_submission();

		if ( $this->is_valid_submission() === false ) {
			return false;
		}

		$this->update_admin_order_total();
		
		$this->save_order_post(); 

		return true;
	}

	/**
	 * Validate submission data entered via the admin page
	 * @since 2.4.1
	 */
	public function validate_admin_submission() {
		global $fdm_controller;

		$this->validation_errors = array();

		if ( ! isset( $_POST['fdm-admin-nonce'] ) 
		    or ! wp_verify_nonce( $_POST['fdm-admin-nonce'], 'fdm-admin-nonce' ) 
		) {
			$this->validation_errors[] = __( 'The request has been rejected because it does not appear to have come from this site.', 'food-and-drink-menu' );
		}

		// Order Data
		$this->name = empty( $_POST['fdm_customer_name'] ) ? '' : sanitize_text_field( $_POST['fdm_customer_name'] );
		$this->phone = empty( $_POST['fdm_customer_phone'] ) ? '' : sanitize_text_field( $_POST['fdm_customer_phone'] );
		$this->email = empty( $_POST['fdm_customer_email'] ) ? '' : sanitize_text_field( $_POST['fdm_customer_email'] );

		// Status changes have a separate guarded form; full edits keep the current status.
		$this->post_status = get_post_status( $this->id );

		$this->estimated_time = empty( $_POST['fdm_order_eta'] ) ? '' : sanitize_text_field( $_POST['fdm_order_eta'] );

		$this->payment_amount = empty( $_POST['fdm_payment_amount'] ) ? 0 : sanitize_text_field( $_POST['fdm_payment_amount'] );
		$this->discount_amount = empty( $_POST['fdm_discount_amount'] ) ? 0 : floatval( sanitize_text_field( $_POST['fdm_discount_amount'] ) );
		$this->tip_amount = empty( $_POST['fdm_tip_amount'] ) ? 0 : floatval( sanitize_text_field( $_POST['fdm_tip_amount'] ) );
		
		$custom_fields = fdm_get_order_custom_field_snapshots( $this );

		foreach ( $custom_fields as $custom_field ) {
			$custom_field = (object) $custom_field;

			if ( $custom_field->type == 'checkbox' ) { $this->custom_fields[ $custom_field->slug ] = ( empty( $_POST[ $custom_field->slug ] ) or ! is_array( $_POST[ $custom_field->slug ] ) ) ? array() : array_map( 'sanitize_text_field', wp_unslash( $_POST[ $custom_field->slug ] ) ); }
			elseif ( $custom_field->type == 'textarea' ) { $this->custom_fields[ $custom_field->slug ] = empty( $_POST[ $custom_field->slug ] ) ? false : sanitize_textarea_field( $_POST[ $custom_field->slug ] ); }
			else { $this->custom_fields[ $custom_field->slug ] = empty( $_POST[ $custom_field->slug ] ) ? false : sanitize_text_field( $_POST[ $custom_field->slug ] ); }
		}

		if ( ! empty( $this->custom_field_snapshots ) ) {
			foreach ( $this->custom_field_snapshots as &$snapshot ) {
				if ( ! empty( $snapshot['slug'] ) && array_key_exists( $snapshot['slug'], $this->custom_fields ) ) {
					$snapshot['value'] = $this->custom_fields[ $snapshot['slug'] ];
				}
			}
			unset( $snapshot );
		}
	}

	/**
	 * Check if submission is valid
	 * @since 2.4.1
	 */
	public function is_valid_submission() {

		if ( !count( $this->validation_errors ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Update the order total, in case an admin updates the tip or discount amounts
	 * @since 2.5.0
	 */
	public function update_admin_order_total() { 
		
		$this->order_total = $this->subtotal + $this->tax_amount + $this->tip_amount + $this->delivery_amount - $this->discount_amount;
	}

	/**
	 * Deletes an item from an order
	 * @since 2.4.1
	 */
	public function process_admin_delete_order_item() {

		$item_id = sanitize_text_field( $_GET['item_id'] );

		if ( ! empty( $this->order_items[ $item_id ] ) ) { 

			unset( $this->order_items[ $item_id ] ); 

			$this->save_order_post();
			
			return true;
		}

		return false;
	}

	/**
	 * Set the different payment portions of the order total
	 * @since 2.5.0
	 */
	public function set_payment_amounts() {

		$this->get_order_total_tax_in();
	}

	/**
	 * Returns the order total, with tax included
	 * @since 2.4.1
	 */
	public function get_order_total_tax_in() {

		if ( ! isset( $this->order_total ) ) { 

			$this->calculate_order_subtotal();

			$this->maybe_apply_discount_code();

			$this->maybe_add_delivery_fee();

			$this->calculate_tax_amount();
		}

		$this->order_total = round( max( 0, $this->subtotal - $this->discount_amount + $this->delivery_amount + $this->tax_amount + floatval( $this->tip_amount ) ), 2 );

		return $this->order_total; 
	}

	/**
	 * Calculate the order subtotal, based on the items in the order
	 * @since 2.4.1
	 */
	public function calculate_order_subtotal() {
		global $fdm_controller;

		$this->subtotal = 0;

		foreach ( $this->get_order_items() as $order_item ) {
			
			$this->subtotal += fdm_calculate_admin_price( $order_item );
		}
	}

	/**
	 * Apply a discount to the order total, if applicable
	 * @since 2.5.0
	 */
	public function maybe_apply_discount_code() {
		global $fdm_controller;
		
		$this->discount_amount = 0;
		if ( empty( $this->discount_code ) ) { return; }

		$discounts = fdm_decode_infinite_table_setting( $fdm_controller->settings->get_setting( 'order-discount-codes' ) );

		foreach( $discounts as $discount ) {

			if ( $discount->code != $this->discount_code ) { continue; }

			if ( ! $discount->enabled ) { continue; }

			if ( (float) $discount->minimum > (float) $this->subtotal )  { continue; }

			if ( ( ! empty( $discount->start_datetime ) and strtotime( $discount->start_datetime ) > time() ) or ( ! empty( $discount->end_datetime ) and strtotime( $discount->end_datetime ) < time() ) ) { continue; }

			$this->discount_amount = round( $discount->type == 'amount' ? max( min( (float) $discount->amount, $this->subtotal ), 0 ) : $this->subtotal * max( min( (float) $discount->amount, 100 ), 0 ) / 100, 2 );
			return;
		}
	}

	/**
	 * Add the delivery amount, if necessary
	 * @since 2.5.0
	 */
	public function maybe_add_delivery_fee() {
		global $fdm_controller;

		$this->delivery_amount = 0;
		if ( $this->delivery and ! empty( $fdm_controller->settings->get_setting( 'ordering-delivery-fee' ) ) ) {

			$this->delivery_amount = floatval( $fdm_controller->settings->get_setting( 'ordering-delivery-fee' ) );
		}
	}

	/**
	 * Calculate any tax on the order
	 * @since 2.5.0
	 */
	public function calculate_tax_amount() {
		global $fdm_controller;
		
		$tax_rate = floatval( preg_replace( '/[^0-9.-]/', '', $fdm_controller->settings->get_setting( 'ordering-tax-rate' ) ) ) / 100;

		$taxable_amount = $this->subtotal - $this->discount_amount + $this->delivery_amount;

		$this->tax_amount = round( max( 0, apply_filters( 'fdm_taxable_amount', $taxable_amount, $this->subtotal, $this->discount_amount, $this->delivery_amount, $this->tip_amount ) * $tax_rate ), 2 );
	}

	/**
	 * Save the payment failure message, so that it is is recorded if using 2-step payments
	 * @since 2.4.8
	 */
	public function payment_failed( $message = '' ) {

		$this->payment_failure_message = $message;

		$this->save_order_post();

		do_action( 'fdm_order_payment_failed', $this );
	}

	/**
	 * Parse the arguments passed in the construction and assign them to
	 * internal variables.
	 * @since 2.1
	 */
	public function parse_args( $args ) {
		foreach ( $args as $key => $val ) {
			switch ( $key ) {

				case 'id' :
					$this->{$key} = esc_attr( $val );

				default :
					$this->{$key} = $val;

			}
		}
	}

}
}

?>
