<?php

/**
 * Class for any section view requested on the front end.
 *
 * @since 1.1
 */

class fdmViewSection extends fdmView {

	public $title = '';
	public $slug = '';
	public $description = '';

	public $background_image_placement = 'hidden';
	public $image_url = '';
	public $title_class = ''; 

	public $min_price = 1000000; 
	public $max_price = 0;

	// Full menu object to capture the section's post data
	public $menu = null;

	// The items being displayed in this section
	public $items;
	public $item_posts = null;

	/** Taxonomy identity inside a rendered menu; public slugs remain unchanged. */
	public function internal_anchor() {
		$instance = $this->menu && ! empty( $this->menu->render_instance_id ) ? $this->menu->render_instance_id : 'standalone';
		return 'fdm-section-' . $instance . '-term-' . absint( $this->id ) . '-' . absint( $this->order ?? 0 );
	}

	const QUERY_BATCH_SIZE = 200;

	/** Fetch menu items once in bounded batches and group them by section. */
	public static function fetch_item_posts( $section_ids ) {
		$section_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $section_ids ) ) ) );
		$grouped = array_fill_keys( $section_ids, array() );
		if ( ! $section_ids ) return $grouped;
		$page = 1;
		do {
			$query = new WP_Query( array(
				'post_type' => FDM_MENUITEM_POST_TYPE, 'post_status' => 'publish',
				'posts_per_page' => self::QUERY_BATCH_SIZE, 'paged' => $page++,
				'order' => 'ASC', 'orderby' => 'menu_order title ID', 'no_found_rows' => true,
				'tax_query' => array( array( 'taxonomy' => 'fdm-menu-section', 'field' => 'term_id', 'terms' => $section_ids ) ),
			) );
			$posts = $query->posts;
			$posts_by_id = array();
			foreach ( $posts as $post ) $posts_by_id[ $post->ID ] = $post;
			$relationships = wp_get_object_terms( array_keys( $posts_by_id ), 'fdm-menu-section', array( 'fields' => 'all_with_object_id' ) );
			$sections_by_post = array();
			if ( ! is_wp_error( $relationships ) ) foreach ( $relationships as $relationship ) {
				$section_id = (int) $relationship->term_id;
				$object_id = (int) $relationship->object_id;
				if ( isset( $grouped[ $section_id ], $posts_by_id[ $object_id ] ) ) $sections_by_post[ $object_id ][ $section_id ] = true;
			}
			// Relationship queries do not preserve the WP_Query item order.
			// Group by membership while appending posts in their saved order.
			foreach ( $posts as $post ) {
				foreach ( isset( $sections_by_post[ $post->ID ] ) ? $sections_by_post[ $post->ID ] : array() as $section_id => $member ) {
					$grouped[ $section_id ][] = $post;
				}
			}
		} while ( self::QUERY_BATCH_SIZE === count( $posts ) );
		wp_reset_postdata();
		return $grouped;
	}

	/**
	 * Initialize the class
	 * @since 1.1
	 */
	public function __construct( $args ) {

		// Parse the values passed
		$this->parse_args( $args );

		// Gather data if it's not already set
		$this->load_section();
	}

	/**
	 * Render the view and enqueue required stylesheets
	 * @since 1.1
	 */
	public function render() {
		global $fdm_controller;

		if ( !isset( $this->id ) ) {
			return;
		}

		if ( !isset( $this->items ) || ( is_array( $this->items ) && !count( $this->items ) ) ) {
			return;
		}

		$this->set_allowed_tags();

		// Add any dependent stylesheets or javascript
		$this->enqueue_assets();

		// Define the classes for this section
		$this->set_classes();

		// Capture output
		ob_start();

		if ( ! empty( $this->stand_alone ) ) { $this->add_custom_styling(); }

		$template = $this->find_template( 'menu-section' );

		if ( $template ) {
			include( $template );
		}
		$output = ob_get_clean();

		return apply_filters( 'fdm_menu_section_output', $output, $this );
	}

	/**
	 * Print the menu items in this section
	 *
	 * @note This just cleans up the template file a bit
	 * @since 1.1
	 */
	public function print_items() {
		$output = '';
		if ( isset( $this->items ) && is_array( $this->items ) ) {
			foreach ( $this->items as $item ) {
				$output .= $item->render();
			}
		}
		return $output;
	}

	/**
	 * Load section data
	 * @since 1.1
	 */
	public function load_section() {
		global $fdm_controller;

		if ( !isset( $this->id ) ) {
			return;
		}

		if ( is_array( $this->item_posts ) ) {
			$item_posts = $this->item_posts;
		} else {
			$grouped = self::fetch_item_posts( array( $this->id ) );
			$item_posts = isset( $grouped[ $this->id ] ) ? $grouped[ $this->id ] : array();
		}
		if ( ! count( $item_posts ) ) {
			return;
		}

		// We go ahead and store all the posts data now to save on db calls
		$this->items = array();
		foreach( $item_posts as $item ) {
			$item = new fdmViewItem(
				array(
					'id' => $item->ID,
					'post' => $item,
					'section' => $this->id
				)
			);

			$item->load_item();

			$this->min_price = min( $this->min_price, $item->min_price );
			$this->max_price = max( $this->max_price, $item->max_price );

			$this->items[] = $item;
		}

		if ( !$this->title ) {
			$section = get_term( $this->id, 'fdm-menu-section' );
			$this->title = $section->name;
			$this->slug = $section->slug;
			$this->description = $section->description;

			$this->image_url = wp_get_attachment_url(get_term_meta( $this->id, '_fdm_menu_section_image', true ));
			$this->background_image_placement = $fdm_controller->settings->get_setting('fdm-menu-section-image-placement');
			$this->title_class = ( ( $fdm_controller->settings->get_setting('fdm-menu-section-image-placement') == 'background' and $this->image_url ) ? 'fdm-hike-up-title' : '' );
		}

		// Load any custom title that has been set for display in this menu
		if ( isset( $this->menu ) && get_class( $this->menu ) == 'fdmViewMenu' ) {
			$menu_post_meta = get_post_meta( $this->menu->id );

			if ( isset( $menu_post_meta['fdm_menu_section_' . $this->id ] ) ) {
				$this->title = $menu_post_meta['fdm_menu_section_' . $this->id ][0];
			}
		}

		do_action( 'fdm_load_section', $this );

	}

	/**
	 * Set the menu section css classes
	 * @since 1.1
	 */
	public function set_classes( $classes = array() ) {
		global $fdm_controller;

		if( 'image' == $fdm_controller->settings->get_setting('fdm-pro-style') ) {
			$number_of_columns = $fdm_controller->settings->get_setting('fdm-image-style-columns');
		}
		elseif( 'refined' == $fdm_controller->settings->get_setting('fdm-pro-style') ) {
			$number_of_columns = $fdm_controller->settings->get_setting('fdm-refined-style-columns');
		}
		elseif( 'luxe' == $fdm_controller->settings->get_setting('fdm-pro-style') ) {
			$number_of_columns = $fdm_controller->settings->get_setting('fdm-luxe-style-columns');
		}
		elseif( 'ordering' == $fdm_controller->settings->get_setting('fdm-pro-style') ) {
			$number_of_columns = $fdm_controller->settings->get_setting('fdm-ordering-style-columns');
		}
		else {
			$number_of_columns = 'one';
		}

		$classes = array_merge(
			$classes,
			array(
				'fdm-section',
				'fdm-sectionid-' . $this->id,
				'fdm-section-' . $this->slug,
				'fdm-section-' . $number_of_columns,
			)
		);

		// Order of this section appearing on this menu
		if ( isset( $this->order ) ) {
			$classes[] = 'fdm-section-' . $this->order;
		}

		$this->classes = apply_filters( 'fdm_menu_section_classes', $classes, $this );
	}

}
