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
		if ( ! $fdm_controller->permissions->can_execute( 'ordering' ) || empty( $fdm_controller->cart ) ) wp_send_json_error( array( 'error' => 'fdm_ordering_suspended', 'msg' => __( 'Ordering is currently unavailable.', 'food-and-drink-menu' ) ), 403 );
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
		return ( new fdmOrderingAjax( $this ) )->add_item_to_cart(  );
	}

	public function admin_add_item_to_order() {
		$this->require_ordering();
		return ( new fdmOrderingAjax( $this ) )->admin_add_item_to_order(  );
	}

	public function delete_item_from_cart() {
		$this->require_ordering();
		return ( new fdmOrderingAjax( $this ) )->delete_item_from_cart(  );
	}

	public function clear_cart() {
		$this->require_ordering();
		return ( new fdmOrderingAjax( $this ) )->clear_cart(  );
	}

	/**
	 * Check whether a discount code is valid, and return the amount/type if it is
	 * @since 2.5.0
	 */
	public function check_discount_code() {
		$this->require_ordering();
		return ( new fdmOrderingAjax( $this ) )->check_discount_code(  );
	}

	public function submit_order() {
		$this->require_ordering();
		return ( new fdmOrderingAjax( $this ) )->submit_order(  );
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
		return ( new fdmOrderingAjax( $this ) )->update_cart_quantities(  );
	}

	public function update_order_progress() {
		$this->require_ordering();
		return ( new fdmOrderingAjax( $this ) )->update_order_progress(  );
	}
}


?>
