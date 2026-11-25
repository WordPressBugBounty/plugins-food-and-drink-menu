<?php

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'fdmImport' ) ) {
/**
 * Secure menu-item spreadsheet import.
 */
class fdmImport {

	const ACTION = 'fdm_import_menu_items';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_install_screen' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'import_menu_items' ) );
	}

	public function register_install_screen() {
		add_submenu_page(
			'edit.php?post_type=fdm-menu',
			__( 'Import Menu', 'food-and-drink-menu' ),
			__( 'Import', 'food-and-drink-menu' ),
			'manage_options',
			'fdm-import',
			array( $this, 'display_import_screen' )
		);
	}

	public function display_import_screen() {
		global $fdm_controller;
		$allowed = $fdm_controller->permissions->can_execute( 'import' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status set by a safe post-action redirect and restricted to a fixed allowlist below.
		$notice = isset( $_GET['fdm_import_notice'] ) ? sanitize_key( wp_unslash( $_GET['fdm_import_notice'] ) ) : '';
		$notices = array(
			'success'       => __( 'Menu items imported successfully.', 'food-and-drink-menu' ),
			'invalid_file'  => __( 'Choose a valid CSV, XLS, or XLSX spreadsheet.', 'food-and-drink-menu' ),
			'invalid_sheet' => __( 'The spreadsheet could not be read or is missing required Title and Price columns.', 'food-and-drink-menu' ),
			'invalid_row'   => __( 'The import stopped because a row contains invalid data or targets a non-menu item.', 'food-and-drink-menu' ),
			'failed'        => __( 'The import could not be completed. No server file path has been exposed.', 'food-and-drink-menu' ),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Import', 'food-and-drink-menu' ); ?></h1>
			<?php if ( isset( $notices[ $notice ] ) ) : ?>
				<div class="notice <?php echo 'success' === $notice ? 'notice-success' : 'notice-error'; ?> is-dismissible"><p><?php echo esc_html( $notices[ $notice ] ); ?></p></div>
			<?php endif; ?>
			<?php if ( $allowed ) : ?>
				<?php $fdm_controller->implementation->call( 'import', array( 'operation' => 'screen' ) ); ?>
			<?php else : ?>
				<div class="fdm-premium-locked"><a href="https://www.fivestarplugins.com/license-payment/?Selected=FDM&amp;Quantity=1&amp;utm_source=fdm_admin_import_page" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Upgrade', 'food-and-drink-menu' ); ?></a> <?php esc_html_e( 'to the premium version to use this feature.', 'food-and-drink-menu' ); ?></div>
			<?php endif; ?>
		</div>
		<?php
	}

	public function import_menu_items() {
		global $fdm_controller;
		if ( ! $fdm_controller->permissions->can_execute( 'import' ) || ! class_exists( 'fdmImportWorker', false ) ) {
			wp_die( esc_html__( 'A compatible FSP Premium Helper is required.', 'food-and-drink-menu' ), '', array( 'response' => 403 ) );
		}
		return ( new fdmImportWorker() )->import_menu_items(  );
	}


	private function process_spreadsheet( $path ) {
		global $fdm_controller;
		if ( ! $fdm_controller->permissions->can_execute( 'import' ) || ! class_exists( 'fdmImportWorker', false ) ) {
			throw new DomainException( 'fdm_implementation_unavailable' );
		}
		return ( new fdmImportWorker() )->process_spreadsheet( $path );
	}


}
}
