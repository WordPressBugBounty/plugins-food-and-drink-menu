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
		$allowed = $fdm_controller->permissions->can_execute( 'export' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Export', 'food-and-drink-menu' ); ?></h1>
			<?php if ( $allowed ) : ?>
				<?php $fdm_controller->implementation->call( 'export', array( 'operation' => 'screen' ) ); ?>
			<?php else : ?>
				<div class="fdm-premium-locked"><a href="https://www.fivestarplugins.com/license-payment/?Selected=FDM&amp;Quantity=1&amp;utm_source=fdm_admin_export_page" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Upgrade', 'food-and-drink-menu' ); ?></a> <?php esc_html_e( 'to the premium version to use this feature.', 'food-and-drink-menu' ); ?></div>
			<?php endif; ?>
		</div>
		<?php
	}

	public function export_menu_items() {
		global $fdm_controller;
		if ( ! $fdm_controller->permissions->can_execute( 'export' ) || ! class_exists( 'fdmExportWorker', false ) ) {
			wp_die( esc_html__( 'A compatible FSP Premium Helper is required.', 'food-and-drink-menu' ), '', array( 'response' => 403 ) );
		}
		return ( new fdmExportWorker() )->export_menu_items(  );
	}

	public function export_orders() {
		global $fdm_controller;
		if ( ! $fdm_controller->permissions->can_execute( 'export' ) || ! class_exists( 'fdmExportWorker', false ) ) {
			wp_die( esc_html__( 'A compatible FSP Premium Helper is required.', 'food-and-drink-menu' ), '', array( 'response' => 403 ) );
		}
		return ( new fdmExportWorker() )->export_orders(  );
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


}
}
