<?php
/**
 * Template tags and shortcodes for use with Food and Drink Menu
 */


/**
 * Create a shortcode to display a menu
 * @since 1.0
 */
function fdm_menu_shortcode( $atts ) {
	global $fdm_controller;

	// Define shortcode attributes
	$menu_atts = array(
		'id' => null,
		'layout' => 'classic',
		'menu_prostyle' => '',
		'show_title' => false,
		'show_content' => false,
		'hide_ordering' => '',
		'sidebar' => ''
	);

	// Create filter so addons can modify the accepted attributes
	$menu_atts = apply_filters( 'fdm_shortcode_menu_atts', $menu_atts );

	extract( shortcode_atts( $menu_atts, $atts ) ); 

	if ( $menu_prostyle and $fdm_controller->permissions->check_permission( 'styling' ) ) {
		
		add_filter( 'fdm-setting-fdm-pro-style', function() use ( $menu_prostyle ) { return $menu_prostyle; } );
	}

	if ( $sidebar == 'no' ) {
		
		add_filter( 'fdm-sidebar', function() { return '0'; } );
	}

	if ( $hide_ordering == 'yes' ) {
		
		add_filter( 'body_class', 'fdm_hide_ordering_features' );
	}

	// Extract the shortcode attributes
	$args = shortcode_atts( $menu_atts, $atts, 'fdm-menu' );

	do_action( 'fdm_menu_init', $args );

	if ( ! empty( $_GET['order_success'] ) ) {

		echo '<div class="fdm-order-payment-message fdm-order-payment-successful">' . esc_html( $fdm_controller->settings->get_setting( 'label-order-success' ) ) . '</div>';
	}

	fdm_possible_order_status_update();

	// Render menu
	fdm_load_view_files();
	$menu = new fdmViewMenu( $args );

	return $menu->render();
}
add_shortcode( 'fdm-menu', 'fdm_menu_shortcode' );

/**
 * Create a shortcode to display a menu section
 * @since 1.0
 */
function fdm_menu_section_shortcode( $atts ) {

	// Define shortcode attributes
	$menu_section_atts = array(
		'id' => null,
		'stand_alone' => true,
	);

	// Create filter so addons can modify the accepted attributes
	$menu_section_atts = apply_filters( 'fdm_shortcode_menu_section_atts', $menu_section_atts );

	// Extract the shortcode attributes
	$args = shortcode_atts( $menu_section_atts, $atts, 'fdm-menu-section' );

	// Render menu
	fdm_load_view_files();
	$menu = new fdmViewSection( $args );

	return $menu->render();
}
add_shortcode( 'fdm-menu-section', 'fdm_menu_section_shortcode' );

/**
 * Create a shortcode to display a menu item
 * @since 1.1
 */
function fdm_menu_item_shortcode( $atts ) {

	// Define shortcode attributes
	$menu_item_atts = array(
		'id' => null,
		'layout' => 'classic',
		'singular' => true
	);

	// Create filter so addons can modify the accepted attributes
	$menu_item_atts = apply_filters( 'fdm_shortcode_menu_item_atts', $menu_item_atts );

	// Extract the shortcode attributes
	$args = shortcode_atts( $menu_item_atts, $atts, 'fdm-menu-item' );

	// Render menu
	fdm_load_view_files();
	$menuitem = new fdmViewItem( $args );

	return $menuitem->render();
}
add_shortcode( 'fdm-menu-item', 'fdm_menu_item_shortcode' );

/**
 * Load files needed for views
 * @since 1.1
 * @note Can be filtered to add new classes as needed
 */
function fdm_load_view_files() {

	$files = array(
		FDM_PLUGIN_DIR . '/views/Base.class.php' // This will load all default classes
	);

	$files = apply_filters( 'fdm_load_view_files', $files );

	foreach( $files as $file ) {
		require_once( $file );
	}

}

/*
 * Assign a globally unique id for each displayed menu
 */
$globally_unique_id = 0;
function fdm_global_unique_id() {
	global $globally_unique_id;
	$globally_unique_id++;
	return 'fdm-menu-' . $globally_unique_id;
}

/**
 * Transform an array of CSS classes into an HTML attribute
 * @since 1.0
 */
function fdm_format_classes($classes) {
	if (count($classes)) {
		return ' class="' . esc_attr( join( ' ', array_map( 'sanitize_html_class', $classes ) ) ) . '"';
	}
}

/**
 * Format the item prices based on the currency symbol settings
 * @since 2.1
 */
function fdm_format_price( $price ) {
	global $fdm_controller;

	$prefix = ( $fdm_controller->settings->get_setting( 'fdm-currency-symbol-location' ) == 'before' ? $fdm_controller->settings->get_setting( 'fdm-currency-symbol' ) : '' );
	$suffix = ( $fdm_controller->settings->get_setting( 'fdm-currency-symbol-location' ) == 'after' ? $fdm_controller->settings->get_setting( 'fdm-currency-symbol' ) : '' );

	$decimals = $fdm_controller->settings->get_setting( 'fdm-disable-price-decimals' ) ? 0 : 2;

	$price = $prefix . ( is_numeric( $price ) ? number_format( (float) $price, $decimals ) : $price ) . $suffix;

	return $price;
}

/**
 * Adds the currently entered tax rate to the price
 * @since 2.4.1
 */
function fdm_add_tax_to_price( $price ) {
	global $fdm_controller;

	$tax_rate = floatval( preg_replace( '/[^0-9.-]/', '', $fdm_controller->settings->get_setting( 'ordering-tax-rate' ) ) ) / 100;

	return round( $price * ( 1 + $tax_rate ), 2 );
}

/**
 * Return the price total based on the size and options selected
 * @since 2.1
 */
function fdm_calculate_cart_price( $menu_item ) {

	$ordering_options = get_post_meta( $menu_item->id, '_fdm_ordering_options', true );
	if ( ! is_array( $ordering_options ) ) { $ordering_options = array(); }

	$selected_options = is_array( $menu_item->selected_options ) ? $menu_item->selected_options : array();

	$price = str_replace( ',', '.', preg_replace( '/[^0-9,.]+/', '', $menu_item->order_price ) );

	foreach ( $selected_options as $selected_option ) { 

		$option_cost = isset( $ordering_options[ $selected_option ]['cost'] ) ? $ordering_options[ $selected_option ]['cost'] : 0;
		$option_price = is_numeric( str_replace( ',', '.', $option_cost ) ) ? str_replace( ',', '.', $option_cost ) : 0;
		$price += $option_price; 
	}

	return apply_filters( 'fdm_cart_price_value', $price, $menu_item );
}

/**
 * Return the price total based on the size and options selected for the admin table
 * @since 2.1.11
 */
function fdm_calculate_admin_price( $order_item ) {
	if ( isset( $order_item->price_snapshot ) && is_numeric( $order_item->price_snapshot ) ) {
		$quantity = isset( $order_item->quantity ) ? max( 1, intval( $order_item->quantity ) ) : 1;
		$price = (float) $order_item->price_snapshot * $quantity;
		foreach ( isset( $order_item->option_snapshots ) && is_array( $order_item->option_snapshots ) ? $order_item->option_snapshots : array() as $option ) {
			$price += ( isset( $option['cost'] ) ? (float) $option['cost'] : 0 ) * $quantity;
		}

		return apply_filters( 'fdm_admin_price_value', $price, $order_item );
	}

	$ordering_options = get_post_meta( $order_item->id, '_fdm_ordering_options', true );
	if ( ! is_array( $ordering_options ) ) { $ordering_options = array(); }
	
	$selected_options = is_array( $order_item->selected_options ) ? $order_item->selected_options : array();

	$talk = '';

	$sanitized_price = str_replace( ',', '.', preg_replace( '/[^0-9,.]+/', '', $order_item->selected_price ) );
	$price = ( ! empty( $sanitized_price ) ? $sanitized_price : 0 ) * ( isset( $order_item->quantity ) ? intval( $order_item->quantity ) : 1 );

	foreach ( $selected_options as $selected_option ) { 

		$option_price = is_numeric( str_replace( ',', '.', $ordering_options[ $selected_option ]['cost'] ) ) ? str_replace( ',', '.', $ordering_options[ $selected_option ]['cost'] ) : 0;
		$price += $option_price * ( isset( $order_item->quantity ) ? intval( $order_item->quantity) : 1 ); 
	}

	return apply_filters( 'fdm_admin_price_value', $price, $order_item );
}


/**
 * Check to see whether an order's status should be updated when the shortcode loads
 * @since 2.1
 */
function fdm_possible_order_status_update() {
	if ( ! isset( $_GET['fdm_action'] ) || 'update_status' !== $_GET['fdm_action'] ) return;
	if ( ! current_user_can( 'manage_fdm_orders' ) ) {
		echo '<div role="alert">' . esc_html__( 'Log in as an authorized staff member to review this order.', 'food-and-drink-menu' ) . '</div>'; return;
	}
	$id = absint( $_GET['order_id'] ?? 0 );
	$post = get_post( $id );
	if ( ! $post || FDM_ORDER_POST_TYPE !== $post->post_type ) return;
	$url = add_query_arg( array( 'page' => 'fdm-add-edit-order', 'order_id' => $id ), admin_url( 'admin.php' ) );
	echo '<div role="status"><a href="' . esc_url( $url ) . '">' . esc_html__( 'Review the order and confirm its status update.', 'food-and-drink-menu' ) . '</a></div>';
}

/**
 * Creates a set of filterable order statuses for orders created by the plugin
 * @since 2.1
 */
function fdm_get_order_statuses() {

	$order_statuses = array( 
		'fdm_order_received' => array(
			'label' => __( 'Received', 'food-and-drink-menu' ),
			'value' => 25,
		),
		'fdm_order_accepted' => array(
			'label' => __( 'Accepted', 'food-and-drink-menu' ),
			'value' => 50,
		),
		'fdm_order_preparing' => array(
			'label' => __( 'Preparing', 'food-and-drink-menu' ),
			'value' => 75,
		),
		'fdm_order_ready' => array(
			'label' => __( 'Ready', 'food-and-drink-menu' ),
			'value' => 100,
		)
	);

	return apply_filters( 'fdm_order_statuses', $order_statuses );
}

function fdm_time_to_decimal( $time = false ) {

	if ( empty( $time ) ) {

	 	$timezone = wp_timezone(); 
		$gmt_seconds_offset = $timezone->getOffset( new DateTime );

		$time = time() + $gmt_seconds_offset; 
	}

	$rounded_minutes = ceil( date( 'i', $time ) / 15 ) * 15;

	return ( $rounded_minutes == 60 ) ? intval( date( 'H', $time + strtotime( '+1 hour' ) ) ) : intval( date( 'H', $time ) ) + $rounded_minutes / 60;
}

if ( ! function_exists( 'fdm_decode_infinite_table_setting' ) ) {
function fdm_decode_infinite_table_setting( $values ) {

	if ( empty( $values ) ) { return array(); }
	
	return is_array( json_decode( html_entity_decode( $values ) ) ) ? json_decode( html_entity_decode( $values ) ) : array();
}
}

// Temporary addition, so that versions of WP before 5.3.0 are supported
if ( ! function_exists( 'wp_timezone') ) {
	function wp_timezone() {
		$timezone_string = get_option( 'timezone_string' );
 
    	if ( ! $timezone_string ) {
        	$offset  = (float) get_option( 'gmt_offset' );
    		$hours   = (int) $offset;
    		$minutes = ( $offset - $hours );

    		$sign      = ( $offset < 0 ) ? '-' : '+';
    		$abs_hour  = abs( $hours );
    		$abs_mins  = abs( $minutes * 60 );
    		$timezone_string = sprintf( '%s%02d:%02d', $sign, $abs_hour, $abs_mins );
    	}

    	return new DateTimeZone( $timezone_string );
	}
}

/**
 * Hide add to cart buttons and the cart
 * @since 2.4.15
 */
function fdm_hide_ordering_features( $classes ) {

	$classes[] = 'fdm-hide-ordering-features';

	return $classes;
}

/**
 * Return the immutable order-time item title with a legacy fallback.
 */
function fdm_get_order_item_title( $order_item ) {
	if ( ! empty( $order_item->title_snapshot ) ) return $order_item->title_snapshot;
	if ( ! empty( $order_item->title ) ) return $order_item->title;
	$current_title = ! empty( $order_item->id ) ? get_the_title( $order_item->id ) : '';
	/* translators: %s: legacy menu item identifier. */
	return $current_title ?: sprintf( __( 'Legacy item data unavailable (item %s)', 'food-and-drink-menu' ), isset( $order_item->id ) ? $order_item->id : __( 'unknown', 'food-and-drink-menu' ) );
}

/**
 * Return immutable order-time options with a current-definition fallback.
 */
function fdm_get_order_item_option_snapshots( $order_item ) {
	if ( isset( $order_item->selected_option_snapshots ) && is_array( $order_item->selected_option_snapshots ) && $order_item->selected_option_snapshots ) return $order_item->selected_option_snapshots;
	if ( isset( $order_item->option_snapshots ) && is_array( $order_item->option_snapshots ) && $order_item->option_snapshots ) return $order_item->option_snapshots;

	$definitions = get_post_meta( $order_item->id, '_fdm_ordering_options', true );
	$definitions = is_array( $definitions ) ? $definitions : array();
	$options = array();
	foreach ( is_array( $order_item->selected_options ) ? $order_item->selected_options : array() as $selected ) {
		if ( ! isset( $definitions[ $selected ] ) ) {
			$options[] = array(
				'id'   => (string) $selected,
				/* translators: %s: legacy option identifier. */
				'name' => sprintf( __( 'Legacy option data unavailable (option %s)', 'food-and-drink-menu' ), $selected ),
				'cost' => null,
			);
			continue;
		}
		$options[] = array(
			'id'   => (string) $selected,
			'name' => isset( $definitions[ $selected ]['name'] ) ? $definitions[ $selected ]['name'] : '',
			'cost' => isset( $definitions[ $selected ]['cost'] ) ? $definitions[ $selected ]['cost'] : 0,
		);
	}
	return $options;
}

/**
 * Build immutable order-time custom-field display data from validated values.
 */
function fdm_create_order_custom_field_snapshots( $values ) {
	global $fdm_controller;
	$values = is_array( $values ) ? $values : array();
	$snapshots = array();
	foreach ( $fdm_controller->settings->get_ordering_custom_fields() as $field ) {
		if ( empty( $field->slug ) || ! array_key_exists( $field->slug, $values ) ) continue;
		$snapshots[] = array(
			'key'    => (string) $field->slug,
			'slug'   => (string) $field->slug,
			'label'  => isset( $field->name ) ? sanitize_text_field( $field->name ) : sanitize_text_field( $field->slug ),
			'type'   => isset( $field->type ) ? sanitize_key( $field->type ) : 'text',
			'values' => isset( $field->values ) ? sanitize_text_field( $field->values ) : '',
			'value'  => $values[ $field->slug ],
		);
	}
	return apply_filters( 'fdm_order_custom_field_snapshots', $snapshots, $values );
}

/**
 * Read custom fields consistently. Legacy fallbacks never rewrite an order.
 */
function fdm_get_order_custom_field_snapshots( $order ) {
	global $fdm_controller;
	if ( ! empty( $order->custom_field_snapshots ) && is_array( $order->custom_field_snapshots ) ) return $order->custom_field_snapshots;

	$values = isset( $order->custom_fields ) && is_array( $order->custom_fields ) ? $order->custom_fields : array();
	$entries = array();
	$known = array();
	foreach ( $fdm_controller->settings->get_ordering_custom_fields() as $field ) {
		if ( empty( $field->slug ) || ! array_key_exists( $field->slug, $values ) ) continue;
		$known[] = (string) $field->slug;
		$entries[] = array(
			'key' => (string) $field->slug, 'slug' => (string) $field->slug,
			'label' => isset( $field->name ) ? (string) $field->name : (string) $field->slug,
			'type' => isset( $field->type ) ? (string) $field->type : 'text',
			'values' => isset( $field->values ) ? (string) $field->values : '', 'value' => $values[ $field->slug ],
		);
	}
	foreach ( $values as $slug => $value ) {
		if ( in_array( (string) $slug, $known, true ) ) continue;
		$entries[] = array(
			'key' => (string) $slug, 'slug' => (string) $slug,
			/* translators: %s: legacy custom-field slug. */
			'label' => sprintf( __( 'Legacy field data unavailable (%s)', 'food-and-drink-menu' ), $slug ),
			'type' => 'text', 'values' => '', 'value' => $value,
		);
	}
	return $entries;
}
