<?php

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'fdmExport' ) ) {
/**
 * Bounded, formula-safe CSV exports.
 */
class fdmExport {

	const MENU_ACTION = 'fdm_export_menu_items';
	const ORDER_ACTION = 'fdm_export_orders';
	const BATCH_SIZE = 200;

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_install_screen' ) );
		add_action( 'admin_post_' . self::MENU_ACTION, array( $this, 'export_menu_items' ) );
		add_action( 'admin_post_' . self::ORDER_ACTION, array( $this, 'export_orders' ) );
	}

	public function register_install_screen() {
		add_submenu_page( 'edit.php?post_type=fdm-menu', __( 'Export Menu', 'food-and-drink-menu' ), __( 'Export', 'food-and-drink-menu' ), 'manage_options', 'fdm-export', array( $this, 'display_export_screen' ) );
	}

	public function display_export_screen() {
		global $fdm_controller;
		$allowed = $fdm_controller->permissions->check_permission( 'export' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Export', 'food-and-drink-menu' ); ?></h1>
			<?php if ( $allowed ) : ?>
				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="display:inline-block;margin-right:8px">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::MENU_ACTION ); ?>" /><?php wp_nonce_field( self::MENU_ACTION ); ?>
					<?php submit_button( __( 'Export Menu Items', 'food-and-drink-menu' ), 'primary', 'submit', false ); ?>
				</form>
				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="display:inline-block">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ORDER_ACTION ); ?>" /><?php wp_nonce_field( self::ORDER_ACTION ); ?>
					<?php submit_button( __( 'Export Orders', 'food-and-drink-menu' ), 'primary', 'submit', false ); ?>
				</form>
			<?php else : ?>
				<div class="fdm-premium-locked"><a href="https://www.fivestarplugins.com/license-payment/?Selected=FDM&amp;Quantity=1&amp;utm_source=fdm_admin_export_page" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Upgrade', 'food-and-drink-menu' ); ?></a> <?php esc_html_e( 'to the premium version to use this feature.', 'food-and-drink-menu' ); ?></div>
			<?php endif; ?>
		</div>
		<?php
	}

	public function export_menu_items() {
		global $fdm_controller;
		$this->authorize( self::MENU_ACTION );
		$fields = $fdm_controller->settings->get_menu_item_custom_fields();
		$headers = array( 'ID', 'Title', 'Description', 'Price', 'Sections' );
		foreach ( $fields as $index => $field ) if ( 'section' !== $field->type ) $headers[] = self::custom_field_header( $field, $index );

		$output = $this->open_csv( 'menu_items_export.csv' );
		$this->write_row( $output, $headers );
		$page = 1;
		do {
			$items = get_posts( array( 'posts_per_page' => self::BATCH_SIZE, 'paged' => $page++, 'post_type' => FDM_MENUITEM_POST_TYPE, 'post_status' => 'publish', 'orderby' => 'ID', 'order' => 'ASC' ) );
			foreach ( $items as $item ) {
				$custom = get_post_meta( $item->ID, '_fdm_menu_item_custom_fields', true );
				$custom = is_array( $custom ) ? $custom : array();
				$terms = get_the_terms( $item->ID, 'fdm-menu-section' );
				$row = array( $item->ID, $item->post_title, $item->post_content, implode( ';', (array) get_post_meta( $item->ID, 'fdm_item_price' ) ), is_array( $terms ) ? implode( ',', wp_list_pluck( $terms, 'name' ) ) : '' );
				foreach ( $fields as $field ) {
					if ( 'section' === $field->type ) continue;
					$value = isset( $custom[ $field->slug ] ) ? $custom[ $field->slug ] : '';
					$row[] = is_array( $value ) ? implode( ',', $value ) : $value;
				}
				$this->write_row( $output, $row );
			}
		} while ( count( $items ) === self::BATCH_SIZE );
		exit;
	}

	public function export_orders() {
		global $fdm_controller;
		$this->authorize( self::ORDER_ACTION );
		$fields = $fdm_controller->settings->get_ordering_custom_fields();
		$headers = array( 'ID', 'Name', 'Email', 'Phone', 'Note', 'Order Items', 'Pickup Time', 'Delivery', 'Payment Amount', 'Tip Amount', 'Receipt ID' );
		foreach ( $fields as $field ) $headers[] = $field->name;
		$headers[] = 'Custom Field Snapshots';

		$output = $this->open_csv( 'orders_export.csv' );
		$this->write_row( $output, $headers );
		$page = 1;
		do {
			$query = new WP_Query( array( 'posts_per_page' => self::BATCH_SIZE, 'paged' => $page++, 'post_type' => FDM_ORDER_POST_TYPE, 'post_status' => array_keys( fdm_get_order_statuses() ), 'orderby' => 'ID', 'order' => 'ASC', 'no_found_rows' => true, 'date_query' => array( array( 'after' => apply_filters( 'fdm_order_export_time', '24 hours ago' ) ) ) ) );
			$posts = $query->posts;
			foreach ( $posts as $post ) {
				$order = new fdmOrderItem();
				if ( ! $order->load( $post ) ) continue;
				$row = array( $order->ID, $order->name, $order->email, $order->phone, $order->note, $this->format_order_items( $order ), $order->pickup_time, $order->delivery ? __( 'Yes', 'food-and-drink-menu' ) : __( 'No', 'food-and-drink-menu' ), $order->payment_amount, $order->tip_amount, $order->receipt_id );
				$snapshots = fdm_get_order_custom_field_snapshots( $order );
				foreach ( $fields as $field ) {
					$value = '';
					foreach ( $snapshots as $snapshot ) if ( $snapshot['slug'] === $field->slug ) { $value = $snapshot['value']; break; }
					$row[] = is_array( $value ) ? implode( ',', $value ) : $value;
				}
				$display_fields = array();
				foreach ( $snapshots as $snapshot ) $display_fields[] = $snapshot['label'] . ': ' . ( is_array( $snapshot['value'] ) ? implode( ',', $snapshot['value'] ) : $snapshot['value'] );
				$row[] = implode( "\n", $display_fields );
				$this->write_row( $output, $row );
			}
			wp_reset_postdata();
		} while ( count( $posts ) === self::BATCH_SIZE );
		exit;
	}

	private function authorize( $nonce_action ) {
		global $fdm_controller;
		if ( 'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) ) wp_die( esc_html__( 'Invalid request method.', 'food-and-drink-menu' ), '', array( 'response' => 405 ) );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'You are not allowed to export this data.', 'food-and-drink-menu' ), '', array( 'response' => 403 ) );
		check_admin_referer( $nonce_action );
		if ( ! $fdm_controller->permissions->check_permission( 'export' ) ) wp_die( esc_html__( 'A paid entitlement is required to export this data.', 'food-and-drink-menu' ), '', array( 'response' => 403 ) );
	}

	private function open_csv( $filename ) {
		if ( headers_sent() ) wp_die( esc_html__( 'The export could not start because output was already sent.', 'food-and-drink-menu' ) );
		while ( ob_get_level() ) ob_end_clean();
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		$output = fopen( 'php://output', 'wb' );
		if ( false === $output ) wp_die( esc_html__( 'The export output could not be opened.', 'food-and-drink-menu' ) );
		return $output;
	}

	private function write_row( $output, $row ) {
		fputcsv( $output, array_map( array( __CLASS__, 'sanitize_export_value' ), $row ), ',', '"', '\\' );
	}

	public static function sanitize_export_value( $value ) {
		if ( is_bool( $value ) ) return $value ? '1' : '0';
		if ( is_int( $value ) || is_float( $value ) ) return $value;
		$value = (string) $value;
		return preg_match( '/^[\x00-\x20]*[=+\-@]/u', $value ) ? "'" . $value : $value;
	}

	public static function custom_field_header( $field, $ordinal = 0 ) {
		$slug = isset( $field->slug ) ? sanitize_key( $field->slug ) : '';
		$label = isset( $field->name ) ? trim( sanitize_text_field( $field->name ) ) : '';
		return $label . ' [fdm:' . $slug . ':' . absint( $ordinal ) . ']';
	}

	private function format_order_items( $order ) {
		$output = '';
		foreach ( $order->get_order_items() as $item ) {
			$output .= '•' . fdm_get_order_item_title( $item ) . "\n";
			$output .= '    ' . max( 1, (int) $item->quantity ) . "\n";
			$output .= '    ' . fdm_calculate_admin_price( $item ) . "\n";
			if ( ! empty( $item->note ) ) $output .= '    ' . __( 'Note:', 'food-and-drink-menu' ) . ' ' . $item->note . "\n";
			$options = fdm_get_order_item_option_snapshots( $item );
			if ( $options ) {
				$output .= '    ' . __( 'Options', 'food-and-drink-menu' ) . "\n";
				foreach ( $options as $option ) $output .= '    •' . $option['name'] . ' (' . fdm_format_price( $option['cost'] ) . ")\n";
			}
			$output .= "\n";
		}
		return $output;
	}
}
}
