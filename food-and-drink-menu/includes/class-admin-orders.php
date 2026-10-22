<?php
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'fdmAdminOrders' ) ) {
/**
 * Class to handle the admin orders page for Order Tracking
 *
 * @since 2.1.0
 */
class fdmAdminOrders {

	// The WP_List_Table child class object of orders to display
	public $orders_table;

	public function __construct() {

		// Add the admin menu
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );

		// Hide the 'Add New' item from the side menu
		add_action( 'admin_head', array( $this, 'hide_add_new_menu_item' ) );
	}

	/**
	 * Add the top-level admin menu page
	 * @since 2.1.0
	 */
	public function add_menu_page() {
		global $fdm_controller;
		if ( ! $fdm_controller->permissions->check_permission( 'ordering' ) ) { return; }

		add_submenu_page(
			'edit.php?post_type=fdm-menu',
			_x( 'Orders', 'Title of admin page that lists orders', 'food-and-drink-menu' ),
			_x( 'Orders', 'Title of orders admin menu item', 'food-and-drink-menu' ),
			'manage_fdm_orders',
			'fdm-orders',
			array( $this, 'show_admin_orders_page' )
		);

		add_submenu_page(
			'fdm-orders',
			_x( 'Add/Edit Order', 'Title of admin page that lets you add or edit an order', 'food-and-drink-menu' ),
			_x( 'Add New', 'Title of the add/edit order admin menu item', 'food-and-drink-menu' ),
			'manage_fdm_orders',
			'fdm-add-edit-order',
			array( $this, 'add_edit_order' )
		);
	}

	/**
	 * Hide the 'Add New' admin page from the WordPress sidebar menu
	 * @since 2.4.1
	 */
	public function hide_add_new_menu_item() {

		remove_submenu_page( 'fdm-orders', 'fdm-add-edit-order' );
	}

	/**
	 * Display the admin orders page
	 * @since 2.1.0
	 */
	public function show_admin_orders_page() {
		global $fdm_controller;
		if ( ! current_user_can( 'manage_fdm_orders' ) || ! $fdm_controller->permissions->check_permission( 'ordering' ) ) return;

		require_once( FDM_PLUGIN_DIR . '/includes/WP_List_Table.OrdersTable.class.php' );
		$this->orders_table = new fdmOrdersTable();
		$this->orders_table->prepare_items();
		?>

		<div class="wrap">
			<h1>
				<?php _e( 'Restaurant Orders', 'food-and-drink-menu' ); ?>
				<!-- <a href="#" class="add-new-h2 page-title-action add-order"><?php _e( 'Add New', 'food-and-drink-menu' ); ?></a> -->
			</h1>

			<?php do_action( 'fdm_orders_table_top' ); ?>
			<form id="fdm-orders-table" method="POST" action="">
				<input type="hidden" name="post_type" value="<?php echo FDM_ORDER_POST_TYPE; ?>" />
				<input type="hidden" name="page" value="fdm-orders">

				<div class="fdm-primary-controls clearfix">
					<div class="fdm-views">
						<?php $this->orders_table->views(); ?>
					</div>
					<?php $this->orders_table->advanced_filters(); ?>
				</div>

				<?php $this->orders_table->display(); ?>
			</form>
			<?php do_action( 'fdm_orders_table_btm' ); ?>
		</div>

		<?php
	}

	/**
	 * Display the order add/edit page
	 * @since 2.4.1
	 */
	public function add_edit_order() {
		global $fdm_controller;

		if ( ! current_user_can( 'manage_fdm_orders' ) || ! $fdm_controller->permissions->check_permission( 'ordering' ) ) { return; }

		$order_id = ! empty( $_POST['fdm_order_id'] ) ? intval( $_POST['fdm_order_id'] ) :
					( ! empty( $_GET['order_id'] ) ? intval( $_GET['order_id'] ) : 0 );

		$order = new fdmOrderItem();

		if ( $order_id ) {

			$order->load( $order_id );
		}

		if ( isset( $_POST['fdm_admin_status_submit'] ) ) {
			check_admin_referer( 'fdm_order_status_' . $order_id );
			$result = $fdm_controller->orders->transition_order_status( $order_id, sanitize_key( wp_unslash( $_POST['fdm_order_status'] ?? '' ) ), array(
				'expected_status' => sanitize_key( wp_unslash( $_POST['fdm_expected_status'] ?? '' ) ), 'revision' => absint( $_POST['fdm_revision'] ?? -1 ),
				'request_id' => sanitize_text_field( wp_unslash( $_POST['fdm_request_id'] ?? '' ) ),
				'correction_reason' => isset( $_POST['fdm_correction'] ) ? sanitize_text_field( wp_unslash( $_POST['fdm_correction_reason'] ?? '' ) ) : '' ) );
			if ( is_wp_error( $result ) ) echo '<div class="notice notice-error" role="alert"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
			$order->load( $order_id );
		}

		if ( isset( $_POST['fdm_admin_order_submit'] ) ) {

			$order->process_admin_order_update();
		}

		if ( isset( $_GET['action'] ) and $_GET['action'] == 'delete_order_item' ) {

			$order->process_admin_delete_order_item();
		}

		fdm_load_view_files();

		$args = array(
			'order'	=> $order
		);

		$admin_order_view = new fdmAdminOrderFormView( $args );

		echo $admin_order_view->render();
	}
}
} // endif;
