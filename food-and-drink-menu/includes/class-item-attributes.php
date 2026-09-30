<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class fdmItemAttributes {
	const MIGRATION_OPTION = 'fdm-attribute-identity-migration';

	public function __construct() {
		add_action( 'init', array( $this, 'migrate_legacy_terms' ), 30 );
		add_action( 'fdm-menu-item-flag_add_form_fields', array( $this, 'render_add_fields' ), 20 );
		add_action( 'fdm-menu-item-flag_edit_form_fields', array( $this, 'render_edit_fields' ), 20 );
		add_action( 'created_fdm-menu-item-flag', array( $this, 'save_fields' ), 20 );
		add_action( 'edited_fdm-menu-item-flag', array( $this, 'save_fields' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_media' ) );
	}

	public static function catalog() {
		return array(
			'fdm:diet:vegetarian' => array( 'Vegetarian', 'dietary' ), 'fdm:diet:vegan' => array( 'Vegan', 'dietary' ),
			'fdm:diet:gluten-free' => array( 'Gluten-free', 'dietary' ), 'fdm:diet:halal' => array( 'Halal', 'dietary' ),
			'fdm:diet:kosher' => array( 'Kosher', 'dietary' ), 'fdm:other:spicy' => array( 'Spicy', 'other' ),
			'fdm:other:alcoholic' => array( 'Alcoholic', 'other' ), 'fdm:other:non-alcoholic' => array( 'Non-alcoholic', 'other' ),
			'fdm:other:decaf' => array( 'Decaf', 'other' ), 'fdm:allergen:celery' => array( 'Celery', 'allergen' ),
			'fdm:allergen:gluten-cereals' => array( 'Cereals containing gluten', 'allergen' ), 'fdm:allergen:crustaceans' => array( 'Crustaceans', 'allergen' ),
			'fdm:allergen:eggs' => array( 'Eggs', 'allergen' ), 'fdm:allergen:fish' => array( 'Fish', 'allergen' ),
			'fdm:allergen:lupin' => array( 'Lupin', 'allergen' ), 'fdm:allergen:milk' => array( 'Milk', 'allergen' ),
			'fdm:allergen:molluscs' => array( 'Molluscs', 'allergen' ), 'fdm:allergen:mustard' => array( 'Mustard', 'allergen' ),
			'fdm:allergen:peanuts' => array( 'Peanuts', 'allergen' ), 'fdm:allergen:sesame' => array( 'Sesame', 'allergen' ),
			'fdm:allergen:soybeans' => array( 'Soybeans', 'allergen' ), 'fdm:allergen:sulphites' => array( 'Sulphur dioxide / sulphites', 'allergen' ),
			'fdm:allergen:tree-nuts' => array( 'Tree nuts', 'allergen' ),
		);
	}

	public function migrate_legacy_terms() {
		if ( 1 === (int) get_option( self::MIGRATION_OPTION, 0 ) || ! taxonomy_exists( 'fdm-menu-item-flag' ) ) return;
		$terms = get_terms( array( 'taxonomy' => 'fdm-menu-item-flag', 'hide_empty' => false ) );
		if ( ! is_wp_error( $terms ) ) foreach ( $terms as $term ) $this->ensure_identity( $term->term_id );
		update_option( self::MIGRATION_OPTION, 1, false );
	}

	private function ensure_identity( $term_id ) {
		if ( ! get_term_meta( $term_id, '_fdm_attribute_id', true ) ) update_term_meta( $term_id, '_fdm_attribute_id', 'fdm:custom:' . wp_generate_uuid4() );
		if ( ! get_term_meta( $term_id, '_fdm_attribute_type', true ) ) update_term_meta( $term_id, '_fdm_attribute_type', 'custom' );
		$legacy = get_option( 'fdm_menu_item_flag_icon_' . $term_id );
		if ( is_array( $legacy ) && ! empty( $legacy['fdm_menu_item_flag_icon'] ) && ! get_term_meta( $term_id, '_fdm_attribute_legacy_icon', true ) ) update_term_meta( $term_id, '_fdm_attribute_legacy_icon', sanitize_key( $legacy['fdm_menu_item_flag_icon'] ) );
	}

	public function render_add_fields() { ?>
		<div class="form-field"><label for="fdm-built-in-attribute"><?php esc_html_e( 'Built-in Item Attribute', 'food-and-drink-menu' ); ?></label><?php $this->render_catalog_select(); ?><p class="description"><?php esc_html_e( 'Optional. Choosing a built-in gives this term a stable dietary or allergen identity. Labels are restaurant controlled and do not establish certification, medical safety, or legal compliance.', 'food-and-drink-menu' ); ?></p></div>
		<div class="form-field"><label for="fdm-attribute-type"><?php esc_html_e( 'Attribute Type', 'food-and-drink-menu' ); ?></label><?php $this->render_type_select( 'custom' ); ?></div>
		<?php $this->render_icon_field( 0 );
	}

	public function render_edit_fields( $term ) {
		$this->ensure_identity( $term->term_id );
		$type = get_term_meta( $term->term_id, '_fdm_attribute_type', true ); ?>
		<tr class="form-field"><th><label><?php esc_html_e( 'Stable Attribute ID', 'food-and-drink-menu' ); ?></label></th><td><code><?php echo esc_html( get_term_meta( $term->term_id, '_fdm_attribute_id', true ) ); ?></code><p class="description"><?php esc_html_e( 'This identity remains unchanged when the term is renamed.', 'food-and-drink-menu' ); ?></p></td></tr>
		<tr class="form-field"><th><label for="fdm-attribute-type"><?php esc_html_e( 'Attribute Type', 'food-and-drink-menu' ); ?></label></th><td><?php $this->render_type_select( $type ); ?></td></tr>
		<tr class="form-field"><th><label><?php esc_html_e( 'Custom Icon', 'food-and-drink-menu' ); ?></label></th><td><?php $this->render_icon_field( $term->term_id ); ?></td></tr>
		<?php
	}

	private function render_catalog_select() {
		echo '<select id="fdm-built-in-attribute" name="fdm_builtin_attribute"><option value="">' . esc_html__( 'Custom attribute', 'food-and-drink-menu' ) . '</option>';
		foreach ( self::catalog() as $id => $definition ) echo '<option value="' . esc_attr( $id ) . '">' . esc_html( $definition[0] ) . '</option>';
		echo '</select>';
	}

	private function render_type_select( $selected ) {
		echo '<select id="fdm-attribute-type" name="fdm_attribute_type">';
		foreach ( array( 'dietary' => __( 'Dietary', 'food-and-drink-menu' ), 'allergen' => __( 'Allergen', 'food-and-drink-menu' ), 'other' => __( 'Other', 'food-and-drink-menu' ), 'custom' => __( 'Custom', 'food-and-drink-menu' ) ) as $value => $label ) echo '<option value="' . esc_attr( $value ) . '" ' . selected( $selected, $value, false ) . '>' . esc_html( $label ) . '</option>';
		echo '</select>';
	}

	private function render_icon_field( $term_id ) {
		$attachment_id = $term_id ? absint( get_term_meta( $term_id, '_fdm_attribute_icon_attachment_id', true ) ) : 0;
		echo '<div class="fdm-attribute-media"><input type="hidden" name="fdm_attribute_icon_attachment_id" value="' . esc_attr( $attachment_id ) . '"><button type="button" class="button fdm-attribute-select-icon">' . esc_html__( 'Choose image', 'food-and-drink-menu' ) . '</button> <button type="button" class="button-link fdm-attribute-remove-icon">' . esc_html__( 'Remove', 'food-and-drink-menu' ) . '</button><p class="description">' . esc_html__( 'Uses a WordPress Media Library image. SVG upload is not enabled.', 'food-and-drink-menu' ) . '</p></div>';
	}

	public function save_fields( $term_id ) {
		if ( ! current_user_can( 'manage_categories' ) ) return;
		// WordPress verifies the taxonomy create/edit nonce before firing these hooks.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$catalog = self::catalog();
		$built_in = isset( $_POST['fdm_builtin_attribute'] ) ? sanitize_text_field( wp_unslash( $_POST['fdm_builtin_attribute'] ) ) : '';
		if ( $built_in && isset( $catalog[ $built_in ] ) && ! get_term_meta( $term_id, '_fdm_attribute_id', true ) ) {
			update_term_meta( $term_id, '_fdm_attribute_id', $built_in );
			update_term_meta( $term_id, '_fdm_attribute_type', $catalog[ $built_in ][1] );
		} else {
			$this->ensure_identity( $term_id );
			$type = isset( $_POST['fdm_attribute_type'] ) ? sanitize_key( wp_unslash( $_POST['fdm_attribute_type'] ) ) : 'custom';
			if ( in_array( $type, array( 'dietary', 'allergen', 'other', 'custom' ), true ) ) update_term_meta( $term_id, '_fdm_attribute_type', $type );
		}
		$attachment_id = isset( $_POST['fdm_attribute_icon_attachment_id'] ) ? absint( wp_unslash( $_POST['fdm_attribute_icon_attachment_id'] ) ) : 0;
		if ( $attachment_id && wp_attachment_is_image( $attachment_id ) ) update_term_meta( $term_id, '_fdm_attribute_icon_attachment_id', $attachment_id );
		elseif ( isset( $_POST['fdm_attribute_icon_attachment_id'] ) ) delete_term_meta( $term_id, '_fdm_attribute_icon_attachment_id' );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	public function enqueue_media() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit-fdm-menu-item-flag' !== $screen->id ) return;
		wp_enqueue_media();
		wp_enqueue_script( 'fdm-attribute-media', FDM_PLUGIN_URL . '/assets/js/attribute-media.js', array( 'jquery' ), FDM_VERSION, true );
	}
}

function fdm_get_item_attributes( $item_id ) {
	$terms = wp_get_post_terms( $item_id, 'fdm-menu-item-flag' );
	if ( is_wp_error( $terms ) ) return array();
	$attributes = array();
	foreach ( $terms as $term ) {
		$id = get_term_meta( $term->term_id, '_fdm_attribute_id', true );
		if ( ! $id ) { $id = 'fdm:custom:' . wp_generate_uuid4(); update_term_meta( $term->term_id, '_fdm_attribute_id', $id ); }
		$attachment_id = absint( get_term_meta( $term->term_id, '_fdm_attribute_icon_attachment_id', true ) );
		$legacy = get_term_meta( $term->term_id, '_fdm_attribute_legacy_icon', true );
		if ( ! $legacy ) { $legacy_option = get_option( 'fdm_menu_item_flag_icon_' . $term->term_id ); $legacy = is_array( $legacy_option ) && isset( $legacy_option['fdm_menu_item_flag_icon'] ) ? $legacy_option['fdm_menu_item_flag_icon'] : ''; }
		$attributes[] = array( 'id' => $id, 'term_id' => $term->term_id, 'label' => $term->name, 'type' => get_term_meta( $term->term_id, '_fdm_attribute_type', true ) ?: 'custom', 'icon_attachment_id' => $attachment_id, 'icon_url' => $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'thumbnail' ) : '', 'legacy_icon' => sanitize_key( $legacy ) );
	}
	return apply_filters( 'fdm_item_attributes', $attributes, $item_id );
}
