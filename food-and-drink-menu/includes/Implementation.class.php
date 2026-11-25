<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Local implementation availability; licensing remains in fdmPermissions. */
class fdmImplementation {
	const API_VERSION = 1;
	private $contract = array();
	private $diagnostic = 'helper_missing';
	private $initialized = false;
	/** Reserved paid template identities are checked before any theme override. */
	private $templates = array(
		'cart-menu-item' => 'ordering', 'content/add_to_cart' => 'ordering',
		'content/cart_header' => 'ordering', 'content/cart_price' => 'ordering',
		'content/cart_quantity' => 'ordering', 'content/custom_fields' => 'custom_fields',
		'content/item-flags' => 'flags', 'content/related_items' => 'related_items',
		'content/related_item_image' => 'related_items', 'content/related_item_price' => 'related_items',
		'content/related_item_title' => 'related_items', 'content/source' => 'sources',
		'menu-ordering-progress' => 'ordering', 'menu-ordering-sidescreen' => 'ordering',
		'menu-ordering-popup' => 'ordering', 'admin-order-form' => 'ordering',
		'admin-order-table-item' => 'ordering', 'premium-filtering' => 'filtering',
		'premium-sorting-options' => 'filtering', 'content/order_options' => 'order_advanced',
	);
	private $services = array(
		'styling' => 'styles', 'premium' => 'item', 'advanced' => 'item',
		'labelling' => 'item', 'flags' => 'item', 'specials' => 'item',
		'sources' => 'item', 'discounts' => 'item', 'related_items' => 'item',
		'custom_fields' => 'item', 'filtering' => 'filtering',
		'import' => 'import', 'export' => 'export', 'ordering' => 'ordering',
		'order_advanced' => 'ordering', 'api_usage' => 'ordering',
	);

	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'initialize' ), -10 );
		add_action( 'admin_notices', array( $this, 'repair_notice' ) );
		add_action( 'admin_menu', array( $this, 'register_order_discovery' ), 99 );
	}

	/** Run after both main files; read authority without HTTP or explicit option writes. */
	public function initialize() {
		global $fdm_controller;
		if ( $this->initialized ) {
			return;
		}
		// No runtime filesystem walk or paid declarations for keyless/denied sites.
		// Retry on a later capability check if activation/migration restores access.
		if ( empty( $fdm_controller->permissions ) || ! $fdm_controller->permissions->check_permission( 'premium' ) ) {
			$this->diagnostic = 'deferred';
			return;
		}
		$this->initialized = true;
		$providers = function_exists( 'fspph_fdm_get_implementation_contract' )
			? array( fspph_fdm_get_implementation_contract( self::API_VERSION ) ) : array();
		$providers = apply_filters( 'fdm_implementation_providers', $providers );
		if ( ! is_array( $providers ) || count( $providers ) > 1 ) {
			$this->diagnostic = 'provider_conflict';
			return;
		}
		if ( ! $providers ) {
			return;
		}
		$contract = reset( $providers );
		if ( ! $this->validate_contract( $contract ) ) {
			return;
		}
		$this->contract = $contract;
		try {
			$booted = call_user_func( $contract['bootstrap'] );
		} catch ( Throwable $exception ) {
			$booted = false;
		}
		if ( true !== $booted ) {
			$this->contract = array();
			$this->diagnostic = 'bootstrap_failed';
			return;
		}
		$this->diagnostic = 'ready';
	}

	private function callback_accepts( $callback, $arguments ) {
		if ( ! is_callable( $callback ) || ( ! is_array( $callback ) && ! is_string( $callback ) && ! $callback instanceof Closure ) ) {
			return false;
		}
		try {
			$reflection = is_array( $callback ) ? new ReflectionMethod( $callback[0], $callback[1] ) : new ReflectionFunction( $callback );
			if ( 1 === $arguments && $reflection->getParameters() ) {
				$type = $reflection->getParameters()[0]->getType();
				if ( $type && ( ! $type instanceof ReflectionNamedType || ! in_array( $type->getName(), array( 'array', 'mixed' ), true ) ) ) {
					return false;
				}
			}
			return $reflection->getNumberOfRequiredParameters() <= $arguments
				&& ( $reflection->isVariadic() || $reflection->getNumberOfParameters() >= $arguments );
		} catch ( Throwable $exception ) {
			return false;
		}
	}

	private function validate_contract( $contract ) {
		$this->diagnostic = 'contract_mismatch';
		if ( ! is_array( $contract ) || self::API_VERSION !== ( $contract['api_version'] ?? null )
			|| 'fsp-premium-helper-fdm' !== ( $contract['provider_id'] ?? null )
			|| ! is_int( $contract['free_api_min'] ?? null ) || ! is_int( $contract['free_api_max'] ?? null )
			|| $contract['free_api_min'] > self::API_VERSION || $contract['free_api_max'] < self::API_VERSION
			|| ! is_string( $contract['module_version'] ?? null ) || ! is_string( $contract['build_id'] ?? null )
			|| ! is_string( $contract['minimum_php'] ?? null ) || ! is_array( $contract['services'] ?? null )
			|| ! is_array( $contract['required_files'] ?? null ) || ! is_array( $contract['class_files'] ?? null )
			|| ! $this->callback_accepts( $contract['bootstrap'] ?? null, 0 ) ) {
			return false;
		}
		if ( version_compare( PHP_VERSION, $contract['minimum_php'], '<' ) ) {
			$this->diagnostic = 'php_unsupported';
			return false;
		}
		$root = is_string( $contract['root_dir'] ?? null ) ? realpath( $contract['root_dir'] ) : false;
		if ( ! $root || ! is_dir( $root ) ) {
			$this->diagnostic = 'files_missing';
			return false;
		}
		foreach ( $contract['required_files'] as $relative ) {
			if ( ! is_string( $relative ) || ! preg_match( '#^[a-zA-Z0-9_ ./-]+$#', $relative )
				|| 0 === strpos( $relative, '/' ) || in_array( '..', explode( '/', $relative ), true ) ) {
				return false;
			}
			$file = realpath( $root . '/' . $relative );
			if ( ! $file || 0 !== strpos( $file, $root . DIRECTORY_SEPARATOR ) || ! is_file( $file ) || ! is_readable( $file ) ) {
				$this->diagnostic = 'files_missing';
				return false;
			}
		}
		foreach ( $contract['class_files'] as $class => $relative ) {
			if ( ! is_string( $class ) || ! in_array( $relative, $contract['required_files'], true ) ) {
				return false;
			}
			if ( class_exists( $class, false ) ) {
				$this->diagnostic = 'class_conflict';
				return false;
			}
		}
		foreach ( $contract['services'] as $service => $callback ) {
			if ( ! in_array( $service, array( 'styles', 'item', 'filtering', 'import', 'export', 'ordering', 'table_context' ), true )
				|| ! $this->callback_accepts( $callback, 1 ) ) {
				return false;
			}
		}
		if ( count( $contract['services'] ) !== 7 ) {
			$this->diagnostic = 'module_incomplete';
			return false;
		}
		return true;
	}

	public function available( $service ) {
		if ( ! $this->initialized ) $this->initialize();
		return 'ready' === $this->diagnostic && isset( $this->contract['services'][ $service ] );
	}

	public function can_execute( $capability ) {
		global $fdm_controller;
		$service = $this->services[ $capability ] ?? '';
		return $service && $this->available( $service ) && isset( $fdm_controller->permissions )
			&& $fdm_controller->permissions->check_permission( $capability );
	}

	/** Internal fixed service calls; callers retain operation-specific security. */
	public function call( $service, $request ) {
		if ( ! $this->available( $service ) || ! is_array( $request ) ) {
			return new WP_Error( 'fdm_implementation_unavailable', __( 'This feature requires a compatible FSP Premium Helper installation.', 'food-and-drink-menu' ) );
		}
		$capabilities = array( 'styles' => 'styling', 'item' => 'premium', 'filtering' => 'filtering', 'import' => 'import', 'export' => 'export', 'ordering' => 'ordering', 'table_context' => 'ordering' );
		if ( ! $this->can_execute( $capabilities[ $service ] ) ) {
			return new WP_Error( 'fdm_paid_access_required', __( 'Verified paid access is required.', 'food-and-drink-menu' ) );
		}
		return call_user_func( $this->contract['services'][ $service ], $request );
	}

	public function diagnostic() {
		return $this->diagnostic;
	}

	/** Late class collisions may only suspend the module, never grant access. */
	public function suspend_class_conflict() {
		$this->diagnostic = 'class_conflict';
		$this->contract = array();
	}

	public function register_order_discovery() {
		if ( $this->can_execute( 'ordering' ) ) return;
		add_submenu_page( 'edit.php?post_type=fdm-menu', __( 'Orders', 'food-and-drink-menu' ), __( 'Orders', 'food-and-drink-menu' ), 'manage_fdm_orders', 'fdm-orders', array( $this, 'order_discovery' ) );
	}

	public function order_discovery() {
		if ( ! current_user_can( 'manage_fdm_orders' ) ) return;
		echo '<div class="wrap"><h1>' . esc_html__( 'Orders', 'food-and-drink-menu' ) . '</h1><p>'
			. esc_html__( 'Ordering requires verified paid access and a compatible FSP Premium Helper. Saved orders and settings have not been removed. Restore access before editing orders or reconciling pending payments.', 'food-and-drink-menu' ) . '</p></div>';
	}

	public function template_allowed( $template ) {
		return ! isset( $this->templates[ $template ] ) || $this->can_execute( $this->templates[ $template ] );
	}

	/** Only exact, preflighted provider template paths can be a default. */
	public function template_path( $template ) {
		if ( ! $this->template_allowed( $template ) || ! isset( $this->templates[ $template ] ) ) return false;
		$relative = 'fdm-templates/' . $template . '.php';
		if ( ! in_array( $relative, $this->contract['required_files'] ?? array(), true ) ) return false;
		$root = realpath( $this->contract['root_dir'] );
		$path = realpath( $root . '/' . $relative );
		return $path && 0 === strpos( $path, $root . DIRECTORY_SEPARATOR ) && is_readable( $path ) ? $path : false;
	}

	public function repair_notice() {
		global $fdm_controller;
		if ( ! current_user_can( 'manage_options' ) || empty( $fdm_controller->permissions )
			|| empty( $fdm_controller->implementation ) || $fdm_controller->implementation !== $this
			|| ! $fdm_controller->permissions->has_license_context() ) {
			return;
		}
		// A late-created provider may not have run its plugins_loaded callback.
		// Only the active provider can report an implementation failure.
		$this->initialize();
		if ( in_array( $this->diagnostic, array( 'ready', 'deferred' ), true ) ) {
			return;
		}
		echo '<div class="notice notice-warning is-dismissible fdm-implementation-notice"><p>'
			. esc_html__( 'Restaurant Menu paid implementation files are unavailable or incompatible. Update or repair FSP Premium Helper. Free menus and saved data remain available; this is not a license-validation failure.', 'food-and-drink-menu' )
			. '</p></div>';
	}
}
