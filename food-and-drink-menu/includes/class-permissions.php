<?php
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'fdmPermissions' ) ) {
/**
 * Class to handle plugin permissions
 *
 * @since 2.0.0
 */
class fdmPermissions {

	private $plugin_permissions;
	private $permission_level;

	public function __construct() {

		if ( ! is_array( get_option( 'fdm-permission-level' ) ) ) { $this->set_permissions(); } 

		$permission_array = get_option( 'fdm-permission-level' );

		$this->permission_level = is_array( $permission_array ) ? reset( $permission_array ) : $permission_array;

		$this->plugin_permissions = array(
			"premium" => 2,
			"advanced" => 2,
			"labelling" => 2,
			"styling" => 2,
			"filtering" => 2,
			"flags" => 2,
			"specials" => 2,
			"sources" => 2,
			"discounts" => 2,
			"related_items" => 2,
			"import" => 2,
			"export" => 2,
			"ordering" => 2,
			"order_advanced" => 3,
			"custom_fields" => 2,
			"api_usage"	=> 3
		);
		foreach ( array( 'fdm-enable-ordering' => 'ordering', 'enable-payment' => 'order_advanced', 'fdm-enable-ordering-options' => 'order_advanced', 'fdm-enable-ordering-progress-display' => 'ordering' ) as $setting => $permission ) {
			add_filter( 'fdm-setting-' . $setting, function( $value ) use ( $permission ) { return $this->check_permission( $permission ) ? $value : false; } );
		}
		add_action( 'admin_notices', array( $this, 'compatibility_notice' ) );
	}

	public function compatibility_notice() {
		if ( ! current_user_can( 'manage_options' ) || ! $this->has_license_context() ) return;
		$contract = $this->get_helper_contract();
		foreach ( array( 'premium', 'ultimate' ) as $tier ) {
			if ( ! empty( $contract[ $tier ]['active'] ) && in_array( $contract[ $tier ]['status'] ?? '', array( 'valid', 'valid_cached' ), true ) ) return;
		}
		if ( ! $contract ) {
			$this->display_license_notice( __( 'Paid features require a compatible FSP Premium Helper with entitlement contract 2. Free menus remain available, and saved settings are preserved. Update or activate the Helper, then check your license.', 'food-and-drink-menu' ) ); return;
		}
		foreach ( array( 'premium', 'ultimate' ) as $tier ) {
			$record = $contract[ $tier ];
			if ( 'missing_or_changed' === ( $record['status'] ?? '' ) && ! get_option( 'fdm' . ( 'ultimate' === $tier ? '-ultimate-license-key' : '-license-key' ) ) ) continue;
			if ( 'valid_cached' === ( $record['status'] ?? '' ) ) $this->display_license_notice( __( 'The license provider is temporarily unavailable. Previously verified access continues only until its original verification deadline.', 'food-and-drink-menu' ), $tier );
			if ( empty( $record['active'] ) ) {
				/* translators: 1: License tier, 2: Short entitlement diagnostic reason. */
				$reasons = array(
					'expired' => __( 'the license has expired', 'food-and-drink-menu' ),
					'invalid' => __( 'the licensing service did not recognize this key', 'food-and-drink-menu' ),
					'unauthorized' => __( 'this key is bound to a different site', 'food-and-drink-menu' ),
					'missing_or_changed' => __( 'access has not been verified for the current key and site', 'food-and-drink-menu' ),
				);
				$reason = $reasons[ $record['status'] ] ?? __( 'the license could not be verified; this does not mean it has expired', 'food-and-drink-menu' );
				$message = sprintf( __( '%1$s access is suspended (%2$s). Your saved settings remain intact.', 'food-and-drink-menu' ), 'premium' === $tier ? __( 'Premium', 'food-and-drink-menu' ) : __( 'Ultimate', 'food-and-drink-menu' ), $reason );
				$this->display_license_notice( $message, $tier );
			}
		}
		if ( ! empty( $contract['premium']['active'] ) && 'expired' === ( $contract['premium']['support_status'] ?? '' ) ) $this->display_license_notice( __( 'Premium support coverage has expired. Your lifetime Premium features remain available.', 'food-and-drink-menu' ), 'premium', 'notice-info' );
	}

	/** Recovery UI is not relevant to Free users or keyless trials; this never grants rights. */
	public function has_license_context() {
		foreach ( array( 'fdm-license-key', 'fdm-ultimate-license-key' ) as $option ) {
			$key = get_option( $option );
			if ( is_string( $key ) && '' !== trim( $key ) && 'no_license_key_entered' !== trim( $key ) ) return true;
		}
		$level = get_option( 'fdm-permission-level', 0 );
		$level = (int) ( is_array( $level ) ? reset( $level ) : $level );
		return $level >= 2 && 'Yes' !== get_option( 'FDM_Trial_Happening' ) && 'Yes' !== get_option( 'FDMU_Trial_Happening' );
	}

	private function display_license_notice( $message, $tier = '', $class = 'notice-warning' ) {
		echo '<div class="notice fdm-license-notice ' . esc_attr( $class ) . '"><p><strong>' . esc_html__( 'Five Star Restaurant Menu:', 'food-and-drink-menu' ) . '</strong> ' . esc_html( $message ) . '</p>';
		if ( $tier ) do_action( 'fsp_fdm_license_recovery', $tier );
		echo '<p><a href="' . esc_url( admin_url( 'edit.php?post_type=fdm-menu&page=fdm-dashboard' ) . '#fdm-license-status' ) . '">' . esc_html__( 'Manage licenses on the Menu dashboard', 'food-and-drink-menu' ) . '</a></p></div>';
	}

	public function set_permissions() {

		if ( is_array( get_option( 'fdm-permission-level' ) ) ) { return; }

		if ( ! empty( get_option( 'fdm-permission-level' ) ) ) { 

			update_option( 'fdm-permission-level', array( get_option( 'fdm-permission-level' ) ) );

			return;
		}

		$menu_objects = get_posts( array( 'post_type' => array( FDM_MENU_POST_TYPE, FDM_MENUITEM_POST_TYPE ) ) );

		$this->permission_level = ( get_option("fdmp_license_key") ? 2 : ( ! empty($menu_objects) ? 1 : 0 ) );

		update_option( 'fdm-permission-level', array( $this->permission_level ) );
	}

	public function check_permission($permission_type = '') {
		if ( ! array_key_exists( $permission_type, $this->plugin_permissions ) ) return false;

		$contract = $this->get_helper_contract();
		return ! empty( $contract ) && in_array( $permission_type, $contract['capabilities'], true );
	}

	private function get_helper_contract() {
		if ( ! function_exists( 'fspph_fdm_supported_entitlement_contracts' ) || ! function_exists( 'fspph_fdm_get_entitlement_contract' ) ) return array();
		$supported = fspph_fdm_supported_entitlement_contracts();
		if ( ! is_array( $supported ) || ! in_array( 2, $supported, true ) ) return array();
		$contract = fspph_fdm_get_entitlement_contract( 2 );
		if ( ! is_array( $contract ) || 2 !== ( $contract['contract_version'] ?? null ) || true !== ( $contract['compatible'] ?? null )
			|| ! is_array( $contract['premium'] ?? null ) || ! is_array( $contract['ultimate'] ?? null )
			|| ! is_bool( $contract['premium']['active'] ?? null ) || ! is_bool( $contract['ultimate']['active'] ?? null )
			|| ! is_array( $contract['capabilities'] ?? null ) ) return array();
		foreach ( array( 'premium', 'ultimate' ) as $tier ) {
			if ( ! is_string( $contract[ $tier ]['status'] ?? null ) || ! is_string( $contract[ $tier ]['reason'] ?? null ) ) return array();
		}
		foreach ( $contract['capabilities'] as $key ) {
			if ( ! is_string( $key ) || ! isset( $this->plugin_permissions[ $key ] ) ) return array();
			if ( $this->plugin_permissions[ $key ] === 3 && ! $contract['ultimate']['active'] ) return array();
			if ( ! $contract['premium']['active'] && ! $contract['ultimate']['active'] ) return array();
		}
		return $contract;
	}

	public function has_compatible_helper_contract( $contract_version = null ) {
		return ( null === $contract_version || 2 === $contract_version ) && ! empty( $this->get_helper_contract() );
	}

	public function update_permissions() {
		$permission = get_option( 'fdm-permission-level', 0 );
		$this->permission_level = (int) ( is_array( $permission ) ? reset( $permission ) : $permission );
	}
}
} // endif
