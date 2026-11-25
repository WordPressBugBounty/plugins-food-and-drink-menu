<?php

/**
 * Base class for any view requested on the front end.
 *
 * @since 1.1
 */
class fdmView extends fdmBase {

	/**
	 * Post type to render
	 */
	public $post_type = null;

	/**
	 * Map types of content to the template which will render them
	 */
	public $content_map = array(
		'title'							 => 'content/title',
		'content'						 => 'content/content',
		'price'							 => 'content/price',
		'image'							 => 'content/image',
		'order_options'			 => 'content/order_options',
	);

	/*
	 * Tags/elements which can be printed
	 */
	public $allowed_tags = array();
	
	/**
	 * Default menu layout
	 */
	public $layout = 'classic';

	/**
	 * Default menu style
	 */
	public $style = 'base';

	/**
	 * Selected prostyle, if any
	 */
	public $menu_prostyle;

	// Locations that should be searched for templates
	public $template_dirs;

	// Default labels, used a fallbacks if no admin inputted label exists
	public $label_defaults = array();

	// The classes that should be added to the main form div
	public $classes;

	/**
	 * Initialize the class
	 * @since 1.1
	 */
	public function __construct( $args ) {

		// Parse the values passed
		$this->parse_args( $args );

		$this->conditional_content_map();
		
		// Filter the content map so addons can customize what and how content
		// is output. Filters are specific to each view, so for this base view
		// you would use the filter 'fdm_content_map_fdmView'
		$this->content_map = apply_filters( 'fdm_content_map_' . get_class( $this ), $this->content_map );

	}

	/**
	 * Add new item data to the content map (points to templates)
	 * @since 2.0
	 */
	function conditional_content_map() {
		global $fdm_controller;

		$explicit_sale = isset( $this->id ) && 'sale' === get_post_meta( $this->id, 'fdm_item_special', true );
		if ( $explicit_sale ) {
			$this->content_map['special'] = 'content/special';
			if ( ! $fdm_controller->settings->get_setting( 'fdm-disable-price' ) ) $this->content_map['price'] = 'content/price-discount';
		}
		if ( $fdm_controller->permissions->can_execute( 'premium' ) ) fdmPremiumItem::content_map( $this );
	}

	/**
	 * Render the view and enqueue required stylesheets
	 *
	 * @note This function should always be overridden by an extending class
	 * @since 1.1
	 */
	public function render() {
		$this->set_error(
			array( 
				'type'		=> 'render() called on wrong class'
			)
		);
	}

	/**
	 * Load a template file for views
	 *
	 * First, it looks in the current theme's /fdm-templates/ directory. Then it
	 * will check a parent theme's /fdm-templates/ directory. If nothing is found
	 * there, it will retrieve the template from the plugin directory.

	 * @since 1.1
	 * @param string template Type of template to load (eg - menu, menu-item)
	 */
	function find_template( $template ) {
		global $fdm_controller;
		if ( ! is_string( $template ) || ! preg_match( '#^[a-zA-Z0-9_/-]+$#', $template )
			|| in_array( '..', explode( '/', $template ), true )
			|| ! $fdm_controller->implementation->template_allowed( $template ) ) return false;
		$default_template = $template;

		$this->template_dirs = array(
			get_stylesheet_directory() . '/' . FDM_TEMPLATE_DIR . '/',
			get_template_directory() . '/' . FDM_TEMPLATE_DIR . '/',
			FDM_PLUGIN_DIR . '/' . FDM_TEMPLATE_DIR . '/'
		);
		
		$this->template_dirs = apply_filters( 'fdm_template_directories', $this->template_dirs );

		if ( isset( $this->layout ) && $this->layout != 'classic' ) {
			$template .= '-' . $this->layout;
		}

		foreach ( $this->template_dirs as $dir ) {
			if ( file_exists( $dir . $template . '.php' ) ) {
				return $dir . $template . '.php';
			}
		}
		// Theme overrides retain precedence; Helper is an exact allowlisted default.
		if ( empty( $this->layout ) || 'classic' === $this->layout ) {
			return $fdm_controller->implementation->template_path( $default_template );
		}

		return false;
	}

	/**
	 * Enqueue stylesheets
	 */
	public function enqueue_assets() {

		global $fdm_controller;

		if ( $fdm_controller->settings->get_setting( 'fdm-style' ) == 'none' ) { return; }

		$enqueued = false;
		if ( $this->style == 'prostyles' ) {
			foreach ( $fdm_controller->prostyles as $style ) {
				if ( $fdm_controller->settings->get_setting('fdm-pro-style') == $style->id ) {
					$style->enqueue_assets();
					$enqueued = true;
				}
			}
		}
		else {
			foreach ( $fdm_controller->styles as $style ) {
				if ( $this->style == $style->id ) {
					$style->enqueue_assets();
					$enqueued = true;
				}
			}
		}
		
		// Fallback to basic style if the selected style does not exist
		// This can happen if they have a custom style defined in a theme, then
		// they switch themes. The setting will still be the custom style, but
		// no entry in $fdm_controller->styles will exist for that style.
		if ( !$enqueued && isset( $fdm_controller->styles['base'] ) ) {
			$fdm_controller->styles['base']->enqueue_assets();
		}
	}

	/**
	 * Retrieve a post in a filter-friendly way
	 *
	 * This function stands in for the get_post() function with a query
	 * that can be filtered by plugins like WPML. It also resets a
	 * view's ID after making the call so that any further requests for
	 * post meta point to the appropriate post.
	 */
	public function get_this_post() {

		if ( empty( $this->id ) || empty( $this->post_type ) ) {
			return;
		}

		$p = new WP_Query(
			array(
				'p' => $this->id,
				'post_type' => $this->post_type
			)
		);

		while ( $p->have_posts() ) {
			$p->next_post();
			$this->post = $p->post;
		}

		wp_reset_postdata();

		// Update the ID if it's been modified by WPML or
		// other query-modifying plugins
		if ( ! empty( $this->post) and ! empty( $this->post->ID ) and $this->post->ID !== $this->id ) {
			$this->id = $this->post->ID;
		}

	}

	/**
	 * Sets the tags/elements which can be printed in this view,
	 * uses the wp_kses function to filter content
	 * @since 2.3.1
	 */
	public function set_allowed_tags() {

		$allowed_tags = wp_kses_allowed_html( 'post' );

		$allowed_atts = array(
			'id' => true,
			'name' => true,
			'type' => true,
			'class' => true,
			'style' => true,
			'value' => true,
			'method' => true,
			'action' => true,
			'data-*' => true,
			'min' => true,
			'max' => true,
			'step' => true,
			'list' => true,
			'required' => true,
			'readonly' => true,
			'placeholder' => true,
		);

		$allowed_tags = array_merge(
			$allowed_tags,
			array(
				'form'  	=> $allowed_atts,
				'input' 	=> $allowed_atts,
				'select'	=> $allowed_atts,
				'option'	=> $allowed_atts,
				'button'	=> $allowed_atts,
				'style'		=> $allowed_atts,
			)
		);

		$this->allowed_tags = apply_filters( 'fdm_kses_allowed_tags', $allowed_tags );
	}

	/**
	 * Returns a specified plugin option
	 * @since 2.3.1
	 */
	public function get_option( $option_name ) {
		global $fdm_controller;

		return $fdm_controller->settings->get_setting( $option_name );
	}

	public function get_label( $label_name ) {
		global $fdm_controller;

		if ( empty( $this->label_defaults ) ) { $this->set_label_defaults(); }

		return ! empty( $fdm_controller->settings->get_setting( $label_name ) ) ? $fdm_controller->settings->get_setting( $label_name ) : $this->label_defaults[ $label_name ];
	}

	public function set_label_defaults() {

		$this->label_defaults = array(
			'label-custom-fields'					=> __( 'Custom Fields', 'food-and-drink-menu' ),
			'label-related-items'					=> __( 'Related Items', 'food-and-drink-menu' ),
			'label-on-sale'							=> __( 'On Sale', 'food-and-drink-menu' ),
			'label-special-offer'					=> __( 'Special Offer', 'food-and-drink-menu' ),
			'label-featured'						=> __( 'Featured', 'food-and-drink-menu' ),
			'label-sold-out'						=> __( 'Sold Out', 'food-and-drink-menu' ),
			'label-sidebar-expand-button'			=> __( 'View Sections', 'food-and-drink-menu' ),

			'label-filtering'						=> __( 'Filtering', 'food-and-drink-menu' ),
			'label-search'							=> __( 'Search', 'food-and-drink-menu' ),
			'label-search-items'					=> __( 'Search Items...', 'food-and-drink-menu' ),
			'label-filtering-price'					=> __( 'Price', 'food-and-drink-menu' ),
			'label-sorting'							=> __( 'Sorting', 'food-and-drink-menu' ),
			'label-name-asc'						=> __( 'Name (A -> Z)', 'food-and-drink-menu' ),
			'label-name-desc'						=> __( 'Name (Z -> A)', 'food-and-drink-menu' ),
			'label-price-asc'						=> __( 'Price (Ascending)', 'food-and-drink-menu' ),
			'label-price-desc'						=> __( 'Price (Descending)', 'food-and-drink-menu' ),
			'label-date-added-asc'					=> __( 'Date Added (Ascending)', 'food-and-drink-menu' ),
			'label-date-added-desc'					=> __( 'Date Added (Descending)', 'food-and-drink-menu' ),
			'label-section-asc'						=> __( 'Section (Ascending)', 'food-and-drink-menu' ),
			'label-section-desc'					=> __( 'Section (Descending)', 'food-and-drink-menu' ),

			'label-add-to-cart'						=> __( 'Add to Cart', 'food-and-drink-menu' ),
			'label-discount'						=> __( 'Discount:', 'food-and-drink-menu' ),
			'label-remove'							=> __( 'Remove', 'food-and-drink-menu' ),
			'label-ordering-price'					=> __( 'Price:', 'food-and-drink-menu' ),
			'label-order-item-details'				=> __( 'Order Item Details', 'food-and-drink-menu' ),
			'label-item-note'						=> __( 'Item Note', 'food-and-drink-menu' ),
			'label-confirm-details'					=> __( 'Confirm Details', 'food-and-drink-menu' ),
			'label-order-progress'					=> __( 'Order Progress', 'food-and-drink-menu' ),
			'label-order-summary'					=> __( 'Order Summary', 'food-and-drink-menu' ),
			'label-your-order'						=> __( 'Your Order', 'food-and-drink-menu' ),
			'label-item-in-cart'					=> __( 'Item in Your Cart', 'food-and-drink-menu' ),
			'label-items-in-cart'					=> __( 'Items in Your Cart', 'food-and-drink-menu' ),
			'label-item-s-in-cart'					=> __( 'Item(s) in Your Cart', 'food-and-drink-menu' ),
			'label-quantity'						=> __( 'Quantity', 'food-and-drink-menu' ),
			'label-clear'							=> __( 'Clear', 'food-and-drink-menu' ),
			'label-sub-total'						=> __( 'Sub-Total', 'food-and-drink-menu' ),
			'label-discount-amount'					=> __( 'Discount', 'food-and-drink-menu' ),
			'label-tax'								=> __( 'Tax', 'food-and-drink-menu' ),
			'label-total'							=> __( 'Total', 'food-and-drink-menu' ),
			'label-check-out'						=> __( 'Check Out', 'food-and-drink-menu' ),
			'label-discount-code'					=> __( 'Discount Code', 'food-and-drink-menu' ),
			'label-apply-discount'					=> __( 'Apply', 'food-and-drink-menu' ),
			'label-pickup-time'						=> __( 'Order Time', 'food-and-drink-menu' ),
			'label-name'							=> __( 'Name', 'food-and-drink-menu' ),
			'label-email'							=> __( 'Email', 'food-and-drink-menu' ),
			'label-phone'							=> __( 'Phone', 'food-and-drink-menu' ),
			'label-order-note'						=> __( 'Order Note', 'food-and-drink-menu' ),
			'label-pickup'							=> __( 'Pickup', 'food-and-drink-menu' ),
			'label-delivery'						=> __( 'Delivery', 'food-and-drink-menu' ),
			'label-delivery-minimum'				=> __( 'Delivery Minimum', 'food-and-drink-menu' ),
			'label-pay-in-store'					=> __( 'Pay in Store', 'food-and-drink-menu' ),
			'label-pay-online'						=> __( 'Pay Online', 'food-and-drink-menu' ),
			'label-submit-order'					=> __( 'Submit Order', 'food-and-drink-menu' ),
			'label-add-another-item'				=> __( '+ Add another item', 'food-and-drink-menu' ),
			'label-pay-via-paypal'					=> __( 'Pay via PayPal', 'food-and-drink-menu' ),
			'label-deposit-placing-hold'			=> __( 'We are only placing a hold for the above amount on your payment instrument. You will be charged later.', 'food-and-drink-menu' ),
			'label-card-number'						=> __( 'Card Number', 'food-and-drink-menu' ),
			'label-cvc'								=> __( 'CVC', 'food-and-drink-menu' ),
			'label-expiration'						=> __( 'Expiration (MM/YYYY)', 'food-and-drink-menu' ),
			'label-tip'								=> __( 'Tip', 'food-and-drink-menu' ),
			'label-delivery-fee'					=> __( 'Delivery Fee', 'food-and-drink-menu' ),
			'label-pay-now'							=> __( 'Pay Now', 'food-and-drink-menu' ),
		);
	}

	public function add_custom_styling() {
		global $fdm_controller;

		echo '<style>';
		if ( $fdm_controller->permissions->can_execute( 'styling' ) ) {
			$fdm_controller->implementation->call( 'styles', array( 'operation' => 'custom_styling', 'view' => $this ) );
		}
			if ( $fdm_controller->settings->get_setting('fdm-display-section-descriptions') != '1' ) { echo '.fdm-section-header p { display: none; }'; }
			if ( $fdm_controller->settings->get_setting('fdm-details-lightbox') != 'disabled' && $fdm_controller->settings->get_setting('fdm-details-lightbox') != '' ) { echo '.fdm-item-title, .fdm-item-image { cursor: pointer; }'; }
		echo  '</style>';
	}

}
