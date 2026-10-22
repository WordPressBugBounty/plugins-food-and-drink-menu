<?php

/**
 * Class to handle everything related to the walk-through that runs on plugin activation
 */

if ( !defined( 'ABSPATH' ) )
	exit;

class fdmAjax {

	public function __construct() {
		
		add_action('wp_head',array( $this, 'frontend_ajax_url' ) );

		add_action( 'wp_ajax_fdm_menu_item_details', array( $this, 'display_menu_item_details' ) );
		add_action( 'wp_ajax_nopriv_fdm_menu_item_details', array( $this, 'display_menu_item_details' ) );

		add_action( 'wp_ajax_fdm_admin_add_to_order', array( $this, 'admin_add_item_to_order' ) );

		add_action( 'wp_ajax_fdm_add_to_cart', array( $this, 'add_item_to_cart' ) );
		add_action( 'wp_ajax_nopriv_fdm_add_to_cart', array( $this, 'add_item_to_cart' ) );

		add_action( 'wp_ajax_fdm_update_cart_qty', array( $this, 'update_cart_quantities' ) );
		add_action( 'wp_ajax_nopriv_fdm_update_cart_qty', array( $this, 'update_cart_quantities' ) );

		add_action( 'wp_ajax_fdm_delete_from_cart', array( $this, 'delete_item_from_cart' ) );
		add_action( 'wp_ajax_nopriv_fdm_delete_from_cart', array( $this, 'delete_item_from_cart' ) );

		add_action( 'wp_ajax_fdm_clear_cart', array( $this, 'clear_cart' ) );
		add_action( 'wp_ajax_nopriv_fdm_clear_cart', array( $this, 'clear_cart' ) );

		add_action( 'wp_ajax_fdm_check_discount_code', array( $this, 'check_discount_code' ) );
		add_action( 'wp_ajax_nopriv_fdm_check_discount_code', array( $this, 'check_discount_code' ) );

		add_action( 'wp_ajax_fdm_submit_order', array( $this, 'submit_order' ) );
		add_action( 'wp_ajax_nopriv_fdm_submit_order', array( $this, 'submit_order' ) );

		add_action( 'wp_ajax_fdm_update_order_progress', array( $this, 'update_order_progress' ) );
		add_action( 'wp_ajax_nopriv_fdm_update_order_progress', array( $this, 'update_order_progress' ) );
	}

	private function require_ordering() {
		global $fdm_controller;
		if ( ! $fdm_controller->permissions->check_permission( 'ordering' ) || empty( $fdm_controller->cart ) ) wp_send_json_error( array( 'error' => 'fdm_ordering_suspended', 'msg' => __( 'Ordering is currently unavailable.', 'food-and-drink-menu' ) ), 403 );
	}

	public function frontend_ajax_url() {
		
		?>
	    	<script type="text/javascript">
	    	    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
	    	</script>
		<?php

	}

	public function display_menu_item_details() {
		
		$post_id = intval( $_POST['post_id'] );

		$item = new fdmViewItem( array( 'id' => $post_id ) );
		$item->set_singular(true);
		$output = $item->render();

		/*// Define the order to print the elements' HTML
		$elements = array(
			'image',
			'title',
			'price',
			'content',
			'custom_fields',
			'related_items'
		);

		foreach ($elements as $element) {
			$template = $item->find_template( $item->content_map[$element] );

			if ( $template ) {
				include( $template );
			}
		}*/

		echo $output;

		die();
	}

	public function add_item_to_cart() {		
		$this->require_ordering();
		global $fdm_controller;

		if ( !check_ajax_referer( 'fdm-ordering', 'nonce' ) ) {
			fdmHelper::bad_nonce_ajax();
		}

		$args = array(
			'item_identifier' 	=> sanitize_text_field( $_POST['item_identifier'] ),
			'id' 				=> intval( $_POST['post_id'] ),
			'selected_options'	=> ( isset( $_POST['selected_options'] ) and $_POST['selected_options'] != '' and is_array( explode(',', $_POST['selected_options'] ) ) ) ?  explode(',', sanitize_text_field( $_POST['selected_options'] ) ) : array() ,
			'note'				=> isset( $_POST['note'] ) ? sanitize_text_field( $_POST['note'] ) : '',
			'selected_price'	=> isset( $_POST['selected_price'] ) ? sanitize_text_field( $_POST['selected_price'] ) : '',
			'quantity' 			=> isset( $_POST['quantity'] ) ? intval( $_POST['quantity'] ) : 1 
		);

		$cart_item = $fdm_controller->cart->add_item( $args );
		if ( is_wp_error( $cart_item ) ) {
			wp_send_json_error(
				array(
					'error' => $cart_item->get_error_code(),
					'msg'   => $cart_item->get_error_message(),
				)
			);
		}

		$item = new fdmViewItem( get_object_vars( $cart_item ) );
		$output = $item->cart_render();

		echo $output;

		die();
	}

	public function admin_add_item_to_order() {
		global $fdm_controller;

		if ( ! check_ajax_referer( 'fdm-admin', 'nonce' ) or ! current_user_can( 'manage_options' ) ) {
			
			fdmHelper::admin_nopriv_ajax();
		}

		$order_id = intval( $_POST['order_id'] );

		$args = array(
			'item_identifier' 	=> sanitize_text_field( $_POST['item_identifier'] ),
			'id' 				=> intval( $_POST['post_id'] ),
			'selected_options'	=> ( isset( $_POST['selected_options'] ) and is_array( $_POST['selected_options'] ) ) ? array_map( 'intval', $_POST['selected_options'] ) : array(),
			'note'				=> isset( $_POST['note'] ) ? sanitize_text_field( $_POST['note'] ) : '',
			'selected_price'	=> isset( $_POST['selected_price'] ) ? sanitize_text_field( $_POST['selected_price'] ) : '',
			'quantity' 			=> isset( $_POST['selected_quantity'] ) ? intval( $_POST['selected_quantity'] ) : 1 
		);

		$pricing = new fdmOrderPricing();
		$args = $pricing->resolve_item( $args );
		if ( is_wp_error( $args ) ) {
			wp_send_json_error( array( 'error' => $args->get_error_code(), 'msg' => $args->get_error_message() ) );
		}

		$order_content = get_post_field( 'post_content', $order_id );

		$cart_items = is_array( unserialize( $order_content ) ) ? unserialize( $order_content ) : array();

		$cart_item = new fdmCartItem( $args );

		$cart_items[ $args['item_identifier'] ] = $cart_item;
		
		$args = array(
			'ID'			=> $order_id,
			'post_content'	=> serialize( $cart_items ),
		);

		wp_update_post( $args );

		$order = new fdmOrderItem();

		$order->load( $order_id );

		$order->calculate_order_subtotal();

		$order->update_admin_order_total();

		$order->save_order_post();

		fdm_load_view_files();

		$admin_view = new fdmAdminOrderFormView( array() );
		$output = $admin_view->render_admin_order_item( $cart_item );

		echo $output;

		die();
	}

	public function delete_item_from_cart() {
		$this->require_ordering();
		global $fdm_controller;

		if ( !check_ajax_referer( 'fdm-ordering', 'nonce' ) ) {
			fdmHelper::bad_nonce_ajax();
		}

		$item_identifier = sanitize_text_field( $_POST['item_identifier'] );

		$fdm_controller->cart->delete_item( $item_identifier );

		die();
	}

	public function clear_cart() {
		$this->require_ordering();
		global $fdm_controller;

		if ( !check_ajax_referer( 'fdm-ordering', 'nonce' ) ) {
			fdmHelper::bad_nonce_ajax();
		}

		$fdm_controller->cart->clear_cart();
	}

	/**
	 * Check whether a discount code is valid, and return the amount/type if it is
	 * @since 2.5.0
	 */
	public function check_discount_code() {
		global $fdm_controller;

		if ( !check_ajax_referer( 'fdm-ordering', 'nonce' ) ) {
			fdmHelper::bad_nonce_ajax();
		}

		$discount_code = trim( sanitize_text_field( $_POST['discount_code'] ) );
		$order_value = floatval( $_POST['order_value'] );

		$discounts = fdm_decode_infinite_table_setting( $fdm_controller->settings->get_setting( 'order-discount-codes' ) );

		foreach ( $discounts as $discount ) {

			if ( $discount->code != $discount_code ) { continue; }

			if ( ! $discount->enabled ) {

				wp_send_json_error(
					array(
						'error'		=> 'discount_code_not_enabled',
						'msg'		=> esc_html( $fdm_controller->settings->get_setting( 'label-discount-not-enabled' ) ),
					)
				);
			}

			if ( ( ! empty( $discount->start_datetime ) and strtotime( $discount->start_datetime ) > time() ) or ( ! empty( $discount->end_datetime ) and strtotime( $discount->end_datetime ) < time() ) ) {
			
				wp_send_json_error(
					array(
						'error'		=> 'discount_code_not_datetime',
						'msg'		=> esc_html( $fdm_controller->settings->get_setting( 'label-discount-not-datetime' ) ),
					)
				);
			}

			if ( $discount->minimum > $order_value ) {

				wp_send_json_error(
					array(
						'error'		=> 'discount_code_minimum_not_met',
						'msg'		=> esc_html( $fdm_controller->settings->get_setting( 'label-discount-minimum-not-met' ) ),
					)
				);
			}

			wp_send_json_success(
				array(
					'discount_amount' 	=> $discount->amount,
					'discount_type'		=> $discount->type,
					'discount_minimum'	=> $discount->minimum,
					'msg'				=> esc_html( $fdm_controller->settings->get_setting( 'label-discount-success' ) ),
				)
			);
		}

		wp_send_json_error(
			array(
				'error'		=> 'discount_code_not_found',
				'msg'		=> esc_html( $fdm_controller->settings->get_setting( 'label-discount-not-found' ) ),
			)
		);
	}

	public function submit_order() {
		global $fdm_controller;

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		$payment_path = $this->resolve_order_payment_path( $nonce );
		if ( is_wp_error( $payment_path ) ) {
			if ( 'fdm_bad_nonce' === $payment_path->get_error_code() ) fdmHelper::bad_nonce_ajax();
			wp_send_json_error( array( 'error' => $payment_path->get_error_code(), 'msg' => $payment_path->get_error_message() ), 400 );
		}

		$args = array(
			'permalink' 	=> isset( $_POST['permalink'] ) ? sanitize_url( wp_unslash( $_POST['permalink'] ) ) : '',
			'name' 			=> isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'email'			=> isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
			'phone'			=> isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
			'note' 			=> isset( $_POST['note'] ) ? sanitize_text_field( wp_unslash( $_POST['note'] ) ) : '',
			'pickup_time'	=> isset( $_POST['pickup_time'] ) ? sanitize_text_field( wp_unslash( $_POST['pickup_time'] ) ) : '',
			'delivery'		=> isset( $_POST['delivery'] ) ? sanitize_key( wp_unslash( $_POST['delivery'] ) ) : '',
			'tip_amount'	=> isset( $_POST['tip_amount'] ) ? sanitize_text_field( wp_unslash( $_POST['tip_amount'] ) ) : '0',
			'discount_code'	=> isset( $_POST['discount_code'] ) ? sanitize_text_field( wp_unslash( $_POST['discount_code'] ) ) : '',
			'payment_path'  => $payment_path,
			'custom_fields' => array()
		);

		if( isset( $_POST['custom_fields'] ) and is_array( $_POST['custom_fields'] ) ) {
			$args['custom_fields'] = $_POST['custom_fields'];
			array_walk_recursive( $args['custom_fields'], function( &$item, $idx ) {
				$item = sanitize_text_field( wp_unslash( $item ) );
			} );
		}

		$order_id = $fdm_controller->orders->submit_order( $args );

		if ( is_wp_error( $order_id ) ) {
			wp_send_json_error(
				array(
					'error' => $order_id->get_error_code(),
					'msg'   => $order_id->get_error_message(),
				)
			);
		}
		elseif ( $order_id ) {
			$order = new fdmOrderItem();
			$order->load( $order_id );
			wp_send_json_success(
				array(
					'order_id'    => $order_id,
					'order_total' => $order->order_total,
					'currency'    => $order->order_currency,
				)
			);
		}
		else {
			wp_send_json_error(
				array(
					'error'		=> 'submit_order_failed',
					'msg'		=> esc_html( $fdm_controller->settings->get_setting( 'label-order-failed' ) ),
				)
			);
		}
	}

	/**
	 * Resolve the only permitted order path from server settings and a path-specific nonce.
	 */
	public function resolve_order_payment_path( $nonce ) {
		global $fdm_controller;

		$is_ordering = (bool) wp_verify_nonce( $nonce, 'fdm-ordering' );
		$is_paypal = (bool) wp_verify_nonce( $nonce, 'fdm-paypal-payment' );
		$is_stripe = (bool) wp_verify_nonce( $nonce, 'fdm-stripe-payment' );
		if ( ! $is_ordering && ! $is_paypal && ! $is_stripe ) {
			return new WP_Error( 'fdm_bad_nonce', __( 'The order request could not be verified.', 'food-and-drink-menu' ) );
		}

		$payment_enabled = (bool) $fdm_controller->settings->get_setting( 'enable-payment' );
		$payment_optional = (bool) $fdm_controller->settings->get_setting( 'payment-optional' );
		$gateway = sanitize_key( (string) $fdm_controller->settings->get_setting( 'ordering-payment-gateway' ) );
		$expected_gateway = ( 'stripe' === $gateway && $is_stripe ) || ( 'paypal' === $gateway && $is_paypal );

		if ( ! $payment_enabled ) {
			return $is_ordering ? 'none' : new WP_Error( 'fdm_payment_path_unavailable', __( 'Online payment is not enabled for this order.', 'food-and-drink-menu' ) );
		}
		if ( $is_paypal || $is_stripe ) {
			return $expected_gateway ? 'online' : new WP_Error( 'fdm_payment_gateway_mismatch', __( 'The selected payment gateway is not available.', 'food-and-drink-menu' ) );
		}
		if ( $is_ordering && $payment_optional ) return 'store';

		return new WP_Error( 'fdm_payment_required', __( 'Online payment is required for this order.', 'food-and-drink-menu' ) );
	}

	public function update_cart_quantities() {
		$this->require_ordering();
		global $fdm_controller;

		if ( !check_ajax_referer( 'fdm-ordering', 'nonce' ) ) {
			fdmHelper::bad_nonce_ajax();
		}

		if ( empty($_POST['quantity'] ) ) return;

		foreach ( $_POST['quantity'] as $item_identifier => $quantity ) {

			$fdm_controller->cart->update_item_quanity( sanitize_text_field( $item_identifier ), intval( $quantity ) );
		}

		die();
	}

	public function update_order_progress() {
		global $fdm_controller;

		$order_statuses = fdm_get_order_statuses();

		// intval() return 0 for undefined which could be a valid post_id
		// $order_id = intval( $_POST['order_id'] );

		$id = absint( $_POST['order_id'] ?? 0 );
		$post = get_post( $id );
		$order_status = ! empty( $fdm_controller->orders ) && $id === $fdm_controller->orders->get_recent_order_id()
			&& $post && FDM_ORDER_POST_TYPE === $post->post_type ? $post->post_status : false;

		if ( $order_status && isset( $order_statuses[ $order_status ] ) ) {
			wp_send_json_success(
				array(
					'status'	=> $order_status,
					'value'		=> $order_statuses[ $order_status ]['value'],
				)
			);
		}
		else {
			wp_send_json_error(
				array(
					'error'		=> 'no_status',
					'msg'		=> __( 'Order does not exist', 'food-and-drink-menu' ),
				)
			);
		}
	}
}


?>
