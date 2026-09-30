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

		$permission_level = (int) $this->permission_level;
		if ( $this->has_compatible_helper_contract() ) {
			$state = fspph_fdm_get_entitlement_state();
			$permission_level = is_array( $state ) && ! empty( $state['active'] )
				? (int) ( isset( $state['permission_level'] ) ? $state['permission_level'] : 0 )
				: 0;
		}

		return $permission_level >= $this->plugin_permissions[ $permission_type ];
	}

	public function has_compatible_helper_contract( $contract_version = null ) {
		if ( ! function_exists( 'fspph_fdm_get_entitlement_state' ) || ! defined( 'FDM_FSPPH_CONTRACT_VERSION' ) ) return false;
		if ( null === $contract_version ) {
			if ( ! function_exists( 'fspph_fdm_entitlement_contract_version' ) ) return false;
			$contract_version = fspph_fdm_entitlement_contract_version();
		}

		return (int) $contract_version >= (int) FDM_FSPPH_CONTRACT_VERSION;
	}

	public function update_permissions() {
		$permission = get_option( 'fdm-permission-level', 0 );
		$this->permission_level = (int) ( is_array( $permission ) ? reset( $permission ) : $permission );
	}
}
} // endif
