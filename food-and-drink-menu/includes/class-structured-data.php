<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/** Canonical, public menu data shared by REST and JSON-LD. */
class fdmStructuredData {
	const VERSION = 1;

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_rest_fields' ) );
	}

	public function register_rest_fields() {
		$schema = $this->rest_schema();
		register_rest_field( FDM_MENU_POST_TYPE, 'fdm', array( 'get_callback' => array( $this, 'rest_menu' ), 'schema' => $schema ) );
		register_rest_field( FDM_MENUITEM_POST_TYPE, 'fdm', array( 'get_callback' => array( $this, 'rest_item' ), 'schema' => $schema ) );
		register_rest_field( 'fdm-menu-section', 'fdm', array( 'get_callback' => array( $this, 'rest_section' ), 'schema' => $schema ) );
	}

	public function rest_schema() {
		return array( 'description' => __( 'Versioned Five Star Restaurant Menu structured data.', 'food-and-drink-menu' ), 'type' => 'object', 'context' => array( 'view', 'edit' ), 'readonly' => true );
	}

	public function rest_menu( $object ) { return $this->get_menu( $object['id'] ); }
	public function rest_item( $object ) { return $this->get_item( $object['id'] ); }
	public function rest_section( $object ) { return $this->get_section( $object['id'] ); }

	public static function parse_display_price( $display ) {
		$display = trim( wp_strip_all_tags( (string) $display ) );
		if ( '' === $display ) return null;
		preg_match_all( '/(?<![0-9])(?:[0-9]{1,3}(?:[ ,][0-9]{3})+|[0-9]+)(?:[\.,][0-9]{1,2})?(?![0-9])/', $display, $matches );
		if ( 1 !== count( $matches[0] ) ) return null;
		return fdmOrderPricing::normalize_money( $matches[0][0] );
	}

	public function get_menu( $menu_id ) {
		$menu = get_post( $menu_id );
		if ( ! $menu || FDM_MENU_POST_TYPE !== $menu->post_type ) return null;
		$section_ids = array();
		foreach ( array( 'one', 'two' ) as $column ) {
			$section_ids = array_merge( $section_ids, array_map( 'absint', array_filter( explode( ',', (string) get_post_meta( $menu_id, 'fdm_menu_column_' . $column, true ) ) ) ) );
		}
		$data = array( 'version' => self::VERSION, 'resource_id' => (int) $menu_id, 'permalink' => get_permalink( $menu_id ), 'section_ids' => array_values( array_unique( array_filter( $section_ids ) ) ) );
		return apply_filters( 'fdm_structured_menu_data', $data, $menu );
	}

	public function get_section( $term_id ) {
		$term = get_term( $term_id, 'fdm-menu-section' );
		if ( ! $term || is_wp_error( $term ) ) return null;
		$data = array( 'version' => self::VERSION, 'resource_id' => (int) $term->term_id, 'name' => $term->name, 'description' => wp_strip_all_tags( $term->description ), 'image' => wp_get_attachment_url( absint( get_term_meta( $term->term_id, '_fdm_menu_section_image', true ) ) ) ?: null );
		return apply_filters( 'fdm_structured_section_data', $data, $term );
	}

	public function get_item( $item_id ) {
		global $fdm_controller;
		$post = get_post( $item_id );
		if ( ! $post || FDM_MENUITEM_POST_TYPE !== $post->post_type ) return null;

		$prices = array();
		foreach ( (array) get_post_meta( $item_id, 'fdm_item_price' ) as $index => $display ) {
			$prices[] = array( 'index' => (int) $index, 'display' => (string) $display, 'amount' => self::parse_display_price( $display ) );
		}
		$special = get_post_meta( $item_id, 'fdm_item_special', true );
		$sale_display = (string) get_post_meta( $item_id, 'fdm_item_price_discount', true );
		$currency = strtoupper( (string) $fdm_controller->settings->get_setting( 'ordering-currency' ) );
		$currency = preg_match( '/^[A-Z]{3}$/', $currency ) ? $currency : null;
		$effective = null;
		$resolved = ( new fdmOrderPricing() )->resolve_price( $item_id, '' );
		if ( ! is_wp_error( $resolved ) ) $effective = array( 'display' => $resolved['display'], 'amount' => $resolved['amount'], 'sale' => ! empty( $resolved['sale'] ) );

		$attributes = array();
		if ( $fdm_controller->permissions->check_permission( 'flags' ) ) $attributes = fdm_get_item_attributes( $item_id );
		$data = array(
			'version' => self::VERSION, 'resource_id' => (int) $item_id, 'permalink' => get_permalink( $item_id ),
			'section_ids' => wp_get_post_terms( $item_id, 'fdm-menu-section', array( 'fields' => 'ids' ) ),
			'prices' => $prices, 'currency' => $currency,
			'sale' => array( 'active' => 'sale' === $special, 'display' => 'sale' === $special ? $sale_display : '', 'amount' => 'sale' === $special ? self::parse_display_price( $sale_display ) : null ),
			'effective_ordering_price' => $effective,
			'image' => get_the_post_thumbnail_url( $item_id, 'full' ) ?: null,
			'attributes' => $attributes,
		);
		return apply_filters( 'fdm_structured_item_data', $data, $post );
	}

	public function get_menu_schema_from_view( $view ) {
		$schema = array( '@context' => 'https://schema.org', '@type' => 'Menu', 'name' => $view->post->post_title, 'hasMenuSection' => array() );
		foreach ( $view->groups as $group ) foreach ( $group as $section ) {
			if ( empty( $section->items ) || ! is_array( $section->items ) ) continue;
			$section_schema = array( '@type' => 'MenuSection', 'name' => $section->title, 'description' => $this->clean_description( $section->description ), 'hasMenuItem' => array() );
			if ( $section->image_url ) $section_schema['image'] = $section->image_url;
			foreach ( $section->items as $item ) {
				$canonical = $this->get_item( $item->id );
				$item_schema = array( '@type' => 'MenuItem', 'name' => $item->title, 'description' => $this->clean_description( $item->content ), 'offers' => array() );
				if ( $item->image ) $item_schema['image'] = $item->image;
				$offer_entries = $canonical['prices'];
				if ( $canonical['sale']['active'] ) array_unshift( $offer_entries, array( 'display' => $canonical['sale']['display'], 'amount' => $canonical['sale']['amount'] ) );
				foreach ( $offer_entries as $price ) if ( null !== $price['amount'] ) {
					$offer = array( '@type' => 'Offer', 'price' => $price['amount'] );
					if ( $canonical['currency'] ) $offer['priceCurrency'] = $canonical['currency'];
					$item_schema['offers'][] = $offer;
				}
				$diets = array_values( array_filter( array_map( array( $this, 'schema_diet' ), $canonical['attributes'] ) ) );
				if ( $diets ) $item_schema['suitableForDiet'] = $diets;
				$section_schema['hasMenuItem'][] = $item_schema;
			}
			$schema['hasMenuSection'][] = $section_schema;
		}
		return apply_filters( 'fdm_structured_menu_schema', $schema, $view );
	}

	private function schema_diet( $attribute ) {
		$map = array( 'fdm:diet:vegan' => 'VeganDiet', 'fdm:diet:vegetarian' => 'VegetarianDiet', 'fdm:diet:gluten-free' => 'GlutenFreeDiet', 'fdm:diet:halal' => 'HalalDiet', 'fdm:diet:kosher' => 'KosherDiet' );
		return isset( $map[ $attribute['id'] ] ) ? 'https://schema.org/' . $map[ $attribute['id'] ] : null;
	}

	private function clean_description( $content ) {
		$content = preg_replace( '/<(script|style)\b[^>]*>.*?<\/\1>/is', '', (string) $content );
		return trim( html_entity_decode( wp_strip_all_tags( strip_shortcodes( $content ) ), ENT_QUOTES, 'UTF-8' ) );
	}
}
