<?php

/**
 * Class for any item view requested on the front end.
 *
 * @since 1.1
 */
class fdmViewItem extends fdmView {

	public $section = '';

	/**
	 * Post type to render
	 */
	public $post_type = FDM_MENUITEM_POST_TYPE;

	/**
	 * Which content elements to display for this item
	 */
	public $elements = array( 'title' );

	public $elements_order = array(
		'special',
		'image',
		'title',
		'price',
		'content',
		'ordering',
		'flags',
		'custom_fields',
		'source',
		'related_items'
	);

	/**
	 * Whether or not we're rendering this item on its own or as part of
	 * a menu.
	 */
	public $singular = false;

	/**
	 * Custom fields for this item with their values
	 */
	public $custom_fields = array();

	/**
	 * Holds all of the different prices available for the current item
	 */
	public $prices = array();

	public $min_price = 1000000; 
	public $max_price = 0;

	// Item content
	public $post;
	public $title;
	public $content;
	public $image;
	public $plain_price;
	public $price;
	public $source_name;
	public $flags;
	public $price_discount;
	public $special;
	public $ordering_available;
	public $ordering_options;
	public $special_title;
	public $related_items;
	public $show_content;
	public $source_desc;
	public $source_address;
	public $source_zoom;
	public $source_map_enabled = false;
	public $source_classes = array();

	// Item order content
	public $selected_options;
	public $note;
	public $quantity;
	public $item_identifier;
	public $selected_price;
	public $order_price;
	public $pricing_version;
	public $snapshot_version;
	public $price_snapshot;
	public $title_snapshot;
	public $option_snapshots = array();
	public $selected_option_snapshots = array();

	public $cart_elements = array( 'title' );
	
	public $cart_elements_order = array(
			'cart_header',
			'image',
			'title',
			'order_options',
			'cart_quantity',
			'cart_price'
	);

	/**
	 * Render the view and enqueue required stylesheets
	 *
	 * @since 1.1
	 */
	public function render() {
		global $fdm_controller;

		if ( !isset( $this->id ) ) {
			return;
		}

		add_filter( 'fdm_menu_item_classes', array( $this, 'fdmp_add_item_classes' ), 10, 2 );

		// Gather data if it's not already set
		if ( !isset( $this->title ) ) {
			$this->load_item();
		}

		// Define css classes to add to this menu item
		$classes = array( 'fdm-item' );

		// 1 is legacy value for lightbox
		if ( in_array( $fdm_controller->settings->get_setting('fdm-details-lightbox'), array( 'lightbox', '1' ) ) ) { 
			$classes[] = 'fdm-item-ajax-open';
		}
		elseif ( 'permalink' == $fdm_controller->settings->get_setting('fdm-details-lightbox') ) { 
			$classes[] = 'fdm-item-newpage-open';
		}

		// Register elements to display
		// Each element is referenced by its variable name (key) and location
		// in the menu item where we want to print it (header, body or footer)
		$elements['title'] = 'body';
		if ( $this->content ) {
			$elements['content'] = 'body';
		}
		if ( isset( $this->image ) ) {
			$elements['image'] = 'body';
			$classes[] = 'fdm-item-has-image';
		}
		if ( isset( $this->price ) && $this->price ) {
			$elements['price'] = 'body';
			$classes[] = 'fdm-item-has-price';
		}
		if ( isset( $this->custom_fields ) and ! empty( $this->custom_fields ) and $this->is_singular()) {
			$elements['custom_fields'] = 'body';
		}
		if ( isset( $this->source_name ) && $this->source_name ) {
			$elements['source'] = 'footer';
		}
		if ( isset( $this->related_items ) and ! empty( $this->related_items ) and $this->is_singular() ) {
			$elements['related_items'] = 'footer';
		}
		if ( isset( $this->special ) && $this->special && $fdm_controller->settings->get_setting('fdm-pro-style') != 'luxe' ) {
			$elements['special'] = 'header';
		}
		if ( isset( $this->special ) && $this->special && $fdm_controller->settings->get_setting('fdm-pro-style') == 'luxe' ) {
			$elements['special'] = 'body';
		}
		if ( isset( $this->price_discount ) && $this->price_discount ) {
			$elements['price'] = 'body';
		}
		if ( isset( $this->flags ) && $this->flags ) {
			$elements['flags'] = 'body';
		}
		if ( isset( $this->ordering_available ) && $this->ordering_available && $fdm_controller->orders->is_open_for_ordering() ) {
			$elements['ordering'] = 'body';
		}
		$attribute_position = $fdm_controller->settings->get_setting( 'fdm-styling-attribute-position' );
		if ( isset( $elements['flags'] ) && in_array( $attribute_position, array( 'below-title', 'below-description' ), true ) ) {
			$this->elements_order = array_values( array_diff( $this->elements_order, array( 'flags' ) ) );
			$after = 'below-title' === $attribute_position ? 'title' : 'content';
			$position = array_search( $after, $this->elements_order, true );
			array_splice( $this->elements_order, false === $position ? count( $this->elements_order ) : $position + 1, 0, array( 'flags' ) );
		}

		// Filter the elements and classes
		$this->elements = apply_filters( 'fdm_menu_item_elements', $elements, $this );
		$this->elements_order = apply_filters( 'fdm_menu_item_elements_order', $this->elements_order, $this );
		$this->classes = apply_filters( 'fdm_menu_item_classes', $classes, $this );

		$this->set_allowed_tags();

		// Add any dependent stylesheets or javascript
		$this->enqueue_assets();
		
		// Capture output
		ob_start();
		if ( $this->singular ) {$this->add_custom_styling();}
		$template = $this->find_template( 'menu-item' );
		if ( $template ) {
			include( $template );
		}
		$output = ob_get_clean();

		return apply_filters( 'fdm_menu_item_output', $output, $this );

	}

	/**
	 * Render the item for cart view
	 *
	 * @since 2.1
	 */
	public function cart_render() {
		global $fdm_controller;
		if ( ! $fdm_controller->permissions->can_execute( 'ordering' ) ) return '';
		return fdmPremiumItem::cart_render( $this );
	}

	/**
	 * Print each of the menu item elements in the defined order
	 *
	 * @note This function just provides us with a cleaner template
	 * @since 1.1
	 */
	public function print_elements( $location ) {

		$output = '';

		foreach( $this->elements_order as $element ) {
			if ( isset( $this->elements[$element] ) && $this->elements[$element] == $location ) {

				// Load the template for this content type
				$template = $this->find_template( $this->content_map[$element] );

				ob_start();
				if ( $template ) {
					include( $template );
				}
				$element_output = ob_get_clean();

				$output .= apply_filters( 'fdm_element_output_' . $element, $element_output, $this );
			}
		}
		return $output;
	}

/**
	 * Print each of the menu item elements for the menu cart in the defined order
	 *
	 * @note This function just provides us with a cleaner template
	 * @since 2.1
	 */
	public function print_cart_elements( $location ) {
		global $fdm_controller;
		if ( ! $fdm_controller->permissions->can_execute( 'ordering' ) ) return '';
		return fdmPremiumItem::print_cart_elements( $this, $location );
	}

	/**
	 * Load item data
	 * @since 1.1
	 */
	public function load_item() {
		global $fdm_controller;

		if ( empty( $this->id ) ) {
			return;
		}

		// If no title is set, we need to gather the core post data first
		if ( empty( $this->title ) ) {
			$this->get_data_from_post();
		}

		if ( empty( $this->image ) ) {
			$image = wp_get_attachment_image_src( get_post_thumbnail_id( $this->id ), 'fdm-item-thumb' );
			if ( isset( $image[0] ) ) {
				$this->image = $image[0];
			}
		}

		if ( ! $fdm_controller->settings->get_setting('fdm-disable-price') ) {
			$this->prices = (array) get_post_meta( $this->id, 'fdm_item_price' );

			if ( $fdm_controller->settings->get_setting( 'fdm-enable-price-filtering' ) ) {
				array_walk( $this->prices,  function( $price ) {

					$this->min_price = min( $this->min_price, $price );
					$this->max_price = max( $this->max_price, $price );
				} );
			}

			$this->plain_price = 1 > count( $this->prices ) ? 0 : max( $this->prices );

			array_walk( $this->prices,  function( &$item ) {
                $item = fdm_format_price( $item );
            } );

			// Load a single price string to be compatible with custom templates
			// created before v1.5.
			$this->price = join(
				apply_filters( 'fdm_prices_separator', _x( '/', 'Separator between multiple prices.', 'food-and-drink-menu' ) ),
				$this->prices
			);
		}

		if ( $fdm_controller->permissions->can_execute( 'premium' ) ) {
			fdmPremiumItem::enrich( $this );
		}

		$stored_special = get_post_meta( $this->id, 'fdm_item_special', true );
		$this->special = false;
		// Explicit Sale remains Free, including its price and label.
		if ( 'sale' === $stored_special ) {
			$this->special = 'sale';
			$this->special_title = $this->get_label( 'label-on-sale' );
			if ( ! $fdm_controller->settings->get_setting( 'fdm-disable-price' ) ) $this->load_discount_price();
		} elseif ( $fdm_controller->permissions->can_execute( 'premium' ) ) {
			fdmPremiumItem::specials( $this, $stored_special );
		}

		// Ordering
		if ( $fdm_controller->settings->get_setting('fdm-enable-ordering') ) {
			$this->ordering_available = $this->special == 'sold_out' ? false : true; //update once the schedulers are working

			$ordering_options = get_post_meta( $this->id, '_fdm_ordering_options', true );
			$this->ordering_options = is_array( $ordering_options ) ? $ordering_options : array();
		}

		do_action( 'fdm_load_item', $this );
	}

	public function load_discount_price() {
		global $fdm_controller;
		if ( 'sale' !== get_post_meta( $this->id, 'fdm_item_special', true ) && ! $fdm_controller->permissions->can_execute( 'discounts' ) ) return '';
			$this->price_discount = get_post_meta( $this->id, 'fdm_item_price_discount', true );

			if ( $this->price_discount ) {

				$price = str_replace( ',', '.', preg_replace( '/[^0-9,.]+/', '', $this->price_discount ) );

				$this->min_price = min( $this->min_price, $price );
				$this->max_price = max( $this->max_price, $price );
			}

			if( ! empty($this->price_discount) ) {
				$this->price_discount = fdm_format_price( $this->price_discount );
			}
	}

	/**
	 * Retrieves data from a post object if it exists or calls the db for it if
	 * not.
	 *
	 * @note This only retrieves core post data, not metadata. @sa load_item()
	 * @since 1.1
	 */
	public function get_data_from_post() {

		// Get the post data. Use WP_Query() and not get_post()
		// to improve compatibility with WPML
		if ( empty( $this->post ) ) {
			$this->get_this_post();
		}

		if ( !empty( $this->post ) ) {
			$this->title = $this->post->post_title;
			$this->content = do_shortcode( wpautop($this->post->post_content) );

			// Update the ID in case it's been modified by WPML or
			// other query-modifying plugins
			$this->id = $this->post->ID;
		}
	}

	/**
	 * Set if this view is of a single item
	 * @since 2.0
	 */
	public function set_singular($singular) {
		$this->singular = $singular;
	}

	/**
	 * Check if this view is of a single item
	 * @since 1.1
	 */
	public function is_singular() {
		if ( isset( $this->singular ) && $this->singular === true ) {
			return true;
		}
		return false;
	}


	/**
	 * Get random items related to the current item
	 * @since 2.0
	 */
	public function get_automatic_related_items() {
		global $fdm_controller;
		if ( ! $fdm_controller->permissions->can_execute( 'related_items' ) ) return array();
		return fdmPremiumItem::get_automatic_related_items( $this );
	}

	/**
	 * Get set items related to the current item
	 * @since 2.0
	 */
	public function get_manual_related_items() {
		global $fdm_controller;
		if ( ! $fdm_controller->permissions->can_execute( 'related_items' ) ) return array();
		return fdmPremiumItem::get_manual_related_items( $this );
	}

	/**
	 * Add CSS classes if new data exists
	 * @since 2.0
	 */
	public function fdmp_add_item_classes( $classes, $item ) {
		global $fdm_controller;
	
		if ( isset( $item->price_discount ) && $item->price_discount ) {
			$classes[] = 'fdm-item-has-price-discount';
		}
	
		if ( $fdm_controller->permissions->can_execute( 'flags' ) ) $classes = fdmPremiumItem::item_classes( $classes );

		return $classes;
	
	}

	/**
	 * Determine the display price for an item being added to the cart
	 * @since 2.1
	 */
	public function load_determine_order_price() {
		global $fdm_controller;
		if ( ! $fdm_controller->permissions->can_execute( 'ordering' ) ) return '';
		return fdmPremiumItem::load_determine_order_price( $this );
	}
}
 
?>
