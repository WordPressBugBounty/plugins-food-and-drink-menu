<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/** Stable table context facade. Tokens and configuration algorithms are owned by Helper. */
class fdmTableContext {
	const COOKIE = 'fdm_table_context';
	private $invalid_entry = false;
	private $worker;
	private function worker() {
		global $fdm_controller;
		if ( ! $fdm_controller->permissions->can_execute( 'ordering' ) || ! class_exists( 'fdmTableContextWorker', false ) ) return null;
		if ( ! $this->worker ) $this->worker = new fdmTableContextWorker( $this );
		return $this->worker;
	}

	public function __construct() {
		add_action( 'template_redirect', array( $this, 'receive_context' ), 1 );
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_post_fdm_save_tables', array( $this, 'save_tables' ) );
	}
	public function table_url( $table ) {
		$worker = $this->worker();
		if ( ! $worker ) { return false; }
		return $worker->table_url( $table );
	}
	public function validate_token( $token, $page_id = null ) {
		$worker = $this->worker();
		if ( ! $worker ) { return false; }
		return $worker->validate_token( $token, $page_id );
	}
	public function receive_context() {
		$worker = $this->worker();
		if ( ! $worker ) { if ( isset( $_GET['fdm_table'] ) ) { $this->clear(); $this->invalid_entry = true; } return; }
		return $worker->receive_context(  );
	}
	public function current() {
		$worker = $this->worker();
		if ( ! $worker ) { return false; }
		return $worker->current(  );
	}
	public function checkout_snapshot( $permalink, $items ) {
		$worker = $this->worker();
		if ( ! $worker ) { return new WP_Error( 'fdm_implementation_unavailable', __( 'Table ordering is temporarily unavailable.', 'food-and-drink-menu' ) ); }
		return $worker->checkout_snapshot( $permalink, $items );
	}
	public function unavailable() {
		$worker = $this->worker();
		if ( ! $worker ) { return $this->invalid_entry || isset( $_COOKIE[ self::COOKIE ] ); }
		return $worker->unavailable(  );
	}
	public function clear() {
		setcookie( self::COOKIE, '', array( 'expires' => time() - 3600, 'path' => COOKIEPATH ?: '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
		unset( $_COOKIE[ self::COOKIE ] );
	}
	public function add_settings_page() { add_submenu_page( 'edit.php?post_type=fdm-menu', __( 'Table ordering settings', 'food-and-drink-menu' ), __( 'Table ordering', 'food-and-drink-menu' ), 'manage_options', 'fdm-table-settings', array( $this, 'render_settings' ) ); }
	public function save_tables() {
		$worker = $this->worker();
		if ( ! $worker ) {
			wp_die( esc_html__( 'Table settings were not saved because table ordering is temporarily unavailable. Existing tables have been preserved. Restore paid access and FSP Premium Helper, then reload the table settings page before trying again.', 'food-and-drink-menu' ), '', array( 'response' => 403 ) );
			return;
		}
		return $worker->save_tables(  );
	}
	public function save_configuration( $rows, $enabled ) {
		$worker = $this->worker();
		if ( ! $worker ) { return new WP_Error( 'fdm_implementation_unavailable', __( 'Table ordering is temporarily unavailable.', 'food-and-drink-menu' ) ); }
		return $worker->save_configuration( $rows, $enabled );
	}
	public function render_settings() {
		$worker = $this->worker();
		if ( ! $worker ) { echo '<div class="wrap"><h1>' . esc_html__( 'Table ordering', 'food-and-drink-menu' ) . '</h1><p>' . esc_html__( 'Table ordering requires verified paid access and a compatible FSP Premium Helper. Saved tables have been preserved.', 'food-and-drink-menu' ) . '</p></div>'; return; }
		return $worker->render_settings(  );
	}

}
