<?php

/**
 * Class for adding the filtering on the front end.
 *
 * @since 2.0
 */

class fdmViewFiltering extends fdmView {

	public $text_search = array();
	public $enable_price_filtering = false;
	public $price_filtering_type = 'textbox'; 
	public $enable_sorting = false;
	public $sorting_types = array();
	public $enable_section_filtering = false;
	public $sections = array();

	// the menu being displayed
	public $menu;

	/**
	 * Initialize the class
	 * @since 2.0
	 */
	public function __construct( $args ) {

		// Parse the values passed
		$this->parse_args( $args );
	}

	/**
	 * Render the view and enqueue required stylesheets
	 * @since 2.0
	 */
	public function render() {
		global $fdm_controller;

		// Gather data if it's not already set
		$this->load_filtering();

		if ( empty( $this->text_search ) && ! $this->enable_price_filtering && ! $this->enable_sorting && ! $this->enable_section_filtering )  {
			return;
		}

		// Add any dependent stylesheets or javascript
		$this->enqueue_assets();

		// Define the classes for this section
		$this->set_classes();

		// Capture output
		ob_start();
		$template = $this->find_template( 'menu-filtering' );
		if ( $template ) {
			include( $template );
		}
		$output = ob_get_clean();

		return apply_filters( 'fdm_menu_filtering_output', $output, $this );
	}


	/**
	 * Load section data
	 * @since 2.0
	 */
	public function load_filtering() {
		global $fdm_controller;

		$paid_filtering = $fdm_controller->permissions->check_permission( 'filtering' );
		$this->text_search = $paid_filtering ? $fdm_controller->settings->get_setting( 'fdm-text-search' ) : array();
		$this->enable_price_filtering = $paid_filtering && $fdm_controller->settings->get_setting( 'fdm-enable-price-filtering' );
		$this->price_filtering_type = $fdm_controller->settings->get_setting( 'fdm-price-filtering-type' );
		$this->enable_section_filtering = (bool) $fdm_controller->settings->get_setting( 'fdm-enable-section-filtering' );
		$name_sorting = ! $paid_filtering && (bool) $fdm_controller->settings->get_setting( 'fdm-enable-name-sorting' );
		$this->enable_sorting = $name_sorting || ( $paid_filtering && $fdm_controller->settings->get_setting( 'fdm-enable-sorting' ) );
		$this->sorting_types = $paid_filtering ? (array) $fdm_controller->settings->get_setting( 'fdm-item-sorting' ) : array();
		if ( $name_sorting && ! in_array( 'name', $this->sorting_types, true ) ) $this->sorting_types[] = 'name';

		if ( $this->enable_section_filtering && ! empty( $this->menu->groups ) ) {
			foreach ( $this->menu->groups as $group ) {
				foreach ( $group as $section ) {
					if ( ! empty( $section->slug ) && ! empty( $section->title ) && ! empty( $section->items ) ) $this->sections[ $section->slug ] = $section->title;
				}
			}
		}
		
		do_action( 'fdm_load_filtering', $this );
	}

	/**
	 * Set the menu section css classes
	 * @since 2.0
	 */
	public function set_classes( $classes = array() ) {
		
		$classes = array_merge(
			$classes,
			array(
				'fdm-filtering'
			)
		);

		$this->classes = apply_filters( 'fdm_menu_filtering_classes', $classes, $this );
	}

	function enqueue_assets() {
		global $fdm_controller;

		$deps = array( 'jquery' );

		if ( $fdm_controller->settings->get_setting( 'fdm-price-filtering-type' ) == 'slider' ) { 

			wp_enqueue_style( 'ewd-urp-jquery-ui', FDM_PLUGIN_URL . '/assets/css/jquery-ui.min.css', FDM_VERSION );
			
			wp_enqueue_script( 'jquery-ui-slider' );

			$deps[] = 'jquery-ui-slider'; 
		}

		wp_enqueue_script( 'fdm-filtering-js', FDM_PLUGIN_URL . '/assets/js/fdm-filtering-js.js', array( 'jquery' ), FDM_VERSION, true );
	}

}
