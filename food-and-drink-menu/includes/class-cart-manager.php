<?php
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'fdmCartManager' ) ) {
/**
 * Class to handle the plugin's ordering cart
 *
 * @since 2.1.0
 */
class fdmCartManager {

	private $cart_items = array();
	private $validation_errors = array();

	public function __construct() {
		
		$this->load_cart_from_cookie();
	}

	public function load_cart_from_cookie() {
		
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON is decoded, type-checked, and every consumed field is sanitized and re-priced below.
		$cookie = isset( $_COOKIE['fdm_cart'] ) ? wp_unslash( $_COOKIE['fdm_cart'] ) : '';
		$fdm_cart_items = $cookie ? (array) json_decode( $cookie, true ) : array();
		
		if ( ! is_array( $fdm_cart_items ) ) { return; }

		$pricing = new fdmOrderPricing();
		foreach ( $fdm_cart_items as $fdm_cart_item ) {
			if ( ! is_array( $fdm_cart_item ) ) { continue; }

			$resolved = $pricing->resolve_item(
				array(
					'id'               => isset( $fdm_cart_item['id'] ) ? absint( $fdm_cart_item['id'] ) : 0,
					'item_identifier'  => isset( $fdm_cart_item['item_identifier'] ) ? sanitize_text_field( $fdm_cart_item['item_identifier'] ) : '',
					'selected_options' => isset( $fdm_cart_item['selected_options'] ) && is_array( $fdm_cart_item['selected_options'] ) ? array_map( 'sanitize_text_field', $fdm_cart_item['selected_options'] ) : array(),
					'selected_price'   => isset( $fdm_cart_item['selected_price'] ) ? sanitize_text_field( $fdm_cart_item['selected_price'] ) : '',
					'note'             => isset( $fdm_cart_item['note'] ) ? sanitize_text_field( $fdm_cart_item['note'] ) : '',
					'quantity'         => isset( $fdm_cart_item['quantity'] ) ? absint( $fdm_cart_item['quantity'] ) : 1,
				)
			);
			if ( is_wp_error( $resolved ) ) {
				$this->validation_errors[] = $resolved;
				continue;
			}

			$this->cart_items[ $resolved['item_identifier'] ] = new fdmCartItem( $resolved );
		}
	}

	public function get_cart_items() {
	
		return $this->cart_items;
	}

	public function get_validation_errors() {
		return $this->validation_errors;
	}

	public function add_item( $item = array() ) {

		$pricing = new fdmOrderPricing();
		$item = $pricing->resolve_item( $item );
		if ( is_wp_error( $item ) ) { return $item; }

		$this->cart_items[ $item['item_identifier'] ] = new fdmCartItem( $item );

		$this->update_cookie();

		return $this->cart_items[ $item['item_identifier'] ];
	}

	public function update_item( $item = array() ) {
		
		if ( ! isset( $item['item_identifier'] ) ) { return false; }

		if ( empty( $this->cart_items[ $item['item_identifier'] ] ) ) { return false; }

		$this->cart_items[ $item['item_identifier'] ]->update( $item );

		$this->update_cookie();
	}

	public function update_item_quanity( $item_identifier, $qty ) {

		$qty = intval( $qty );
		$qty = ( $qty > 0 && $qty < 10001 ) ? $qty : 1;

		$args = array(
			'item_identifier' => $item_identifier,
			'quantity'        => $qty
		);

		$this->update_item( $args );

		$this->update_cookie();
	}

	public function delete_item( $item_identifier ) {
		
		unset( $this->cart_items[ $item_identifier ] );

		$this->update_cookie();
	}

	public function update_cookie() {
		
		setcookie(
			'fdm_cart',
			wp_json_encode( $this->cart_items ),
			array(
				'expires'  => time() + 4 * HOUR_IN_SECONDS,
				'path'     => '/',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	public function clear_cart() {
		
		if ( isset( $_COOKIE['fdm_cart'] ) ) { unset( $_COOKIE['fdm_cart'] ); }

		setcookie(
			'fdm_cart',
			'',
			array(
				'expires'  => time() - HOUR_IN_SECONDS,
				'path'     => '/',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}
}
} // endif
