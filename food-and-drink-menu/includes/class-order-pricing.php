<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'fdmOrderPricing' ) ) {
/**
 * Resolve menu-item selections and money values from authoritative server data.
 *
 * Browser and cookie values are selectors only. They never establish price or
 * option cost.
 */
class fdmOrderPricing {

	const SNAPSHOT_VERSION = 1;
	const MAX_QUANTITY = 10000;

	/**
	 * Resolve one requested cart item to canonical values.
	 *
	 * @param array $item Requested item data.
	 * @return array|WP_Error
	 */
	public function resolve_item( $item ) {
		$item_id = isset( $item['id'] ) ? absint( $item['id'] ) : 0;
		$post = $item_id ? get_post( $item_id ) : null;

		if ( ! $post || FDM_MENUITEM_POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return new WP_Error( 'fdm_invalid_menu_item', __( 'This menu item is no longer available. Please refresh the menu.', 'food-and-drink-menu' ) );
		}

		$quantity_value = isset( $item['quantity'] ) ? $item['quantity'] : 1;
		if ( ! is_scalar( $quantity_value ) || ! preg_match( '/^[0-9]+$/', (string) $quantity_value ) ) {
			return new WP_Error( 'fdm_invalid_quantity', __( 'Choose a valid item quantity.', 'food-and-drink-menu' ) );
		}
		$quantity = (int) $quantity_value;
		if ( $quantity < 1 || $quantity > self::MAX_QUANTITY ) {
			return new WP_Error( 'fdm_invalid_quantity', __( 'Choose a valid item quantity.', 'food-and-drink-menu' ) );
		}

		$price = $this->resolve_price( $item_id, isset( $item['selected_price'] ) ? $item['selected_price'] : '' );
		if ( is_wp_error( $price ) ) {
			return $price;
		}

		$options = $this->resolve_options( $item_id, isset( $item['selected_options'] ) ? $item['selected_options'] : array() );
		if ( is_wp_error( $options ) ) {
			return $options;
		}

		$item_identifier = isset( $item['item_identifier'] ) ? sanitize_text_field( $item['item_identifier'] ) : '';
		if ( '' === $item_identifier ) {
			$item_identifier = $item_id . '-' . wp_generate_uuid4();
		}

		return array(
			'id'               => $item_id,
			'title'            => $post->post_title,
			'item_identifier'  => $item_identifier,
			'selected_price'   => $price['display'],
			'selected_options' => wp_list_pluck( $options, 'id' ),
			'note'             => isset( $item['note'] ) ? sanitize_text_field( $item['note'] ) : '',
			'quantity'         => $quantity,
			'pricing_version'  => self::SNAPSHOT_VERSION,
			'snapshot_version' => self::SNAPSHOT_VERSION,
			'price_snapshot'   => $price['amount'],
			'title_snapshot'   => $post->post_title,
			'option_snapshots' => $options,
			'selected_option_snapshots' => $options,
		);
	}

	/**
	 * Revalidate every item before an order is inserted.
	 *
	 * @param array $items Cart items.
	 * @return array|WP_Error
	 */
	public function revalidate_cart_items( $items ) {
		$canonical = array();

		foreach ( (array) $items as $key => $item ) {
			$request = is_object( $item ) ? get_object_vars( $item ) : (array) $item;
			$resolved = $this->resolve_item( $request );
			if ( is_wp_error( $resolved ) ) {
				return new WP_Error( 'fdm_stale_cart', __( 'Your menu changed while you were ordering. Please review your cart and try again.', 'food-and-drink-menu' ), array( 'cause' => $resolved->get_error_code() ) );
			}

			$identifier = ! empty( $resolved['item_identifier'] ) ? $resolved['item_identifier'] : $key;
			$canonical[ $identifier ] = new fdmCartItem( $resolved );
		}

		if ( ! $canonical ) {
			return new WP_Error( 'fdm_empty_cart', __( 'Your cart is empty.', 'food-and-drink-menu' ) );
		}

		return $canonical;
	}

	/**
	 * Normalize a stored or submitted money value for comparison/calculation.
	 *
	 * @param mixed $value Money value.
	 * @return float|null
	 */
	public static function normalize_money( $value ) {
		$value = trim( wp_strip_all_tags( (string) $value ) );
		$value = preg_replace( '/[^0-9,\.\-]/', '', $value );
		if ( '' === $value ) {
			return null;
		}

		$comma = strrpos( $value, ',' );
		$dot = strrpos( $value, '.' );
		if ( false !== $comma && false !== $dot ) {
			if ( $comma > $dot ) {
				$value = str_replace( '.', '', $value );
				$value = str_replace( ',', '.', $value );
			} else {
				$value = str_replace( ',', '', $value );
			}
		} elseif ( false !== $comma ) {
			$value = 3 === strlen( $value ) - $comma - 1 ? str_replace( ',', '', $value ) : str_replace( ',', '.', $value );
		}

		if ( ! is_numeric( $value ) ) {
			return null;
		}

		$amount = (float) $value;
		if ( ! is_finite( $amount ) || $amount < 0 ) {
			return null;
		}

		return round( $amount, 2 );
	}

	public static function to_minor_units( $amount, $currency ) {
		$amount = self::normalize_money( $amount );
		if ( null === $amount ) {
			return null;
		}

		return 'JPY' === strtoupper( (string) $currency ) ? (int) round( $amount ) : (int) round( $amount * 100 );
	}

	public function resolve_price( $item_id, $selector ) {
		$candidates = array();
		$special = get_post_meta( $item_id, 'fdm_item_special', true );
		$sale = get_post_meta( $item_id, 'fdm_item_price_discount', true );
		$sale_amount = self::normalize_money( $sale );

		if ( 'sale' === $special && null !== $sale_amount ) {
			$candidates[] = array( 'display' => (string) $sale, 'amount' => $sale_amount, 'sale' => true );
		}

		foreach ( (array) get_post_meta( $item_id, 'fdm_item_price' ) as $stored_price ) {
			$amount = self::normalize_money( $stored_price );
			if ( null !== $amount ) {
				$candidates[] = array( 'display' => (string) $stored_price, 'amount' => $amount, 'sale' => false );
			}
		}

		if ( ! $candidates ) {
			return new WP_Error( 'fdm_missing_price', __( 'This menu item does not currently have a valid price.', 'food-and-drink-menu' ) );
		}

		if ( '' === trim( (string) $selector ) ) {
			return reset( $candidates );
		}

		$selected_amount = self::normalize_money( $selector );
		if ( null === $selected_amount ) {
			return new WP_Error( 'fdm_invalid_price', __( 'The selected price is no longer available.', 'food-and-drink-menu' ) );
		}

		$matches = array_values( array_filter( $candidates, function ( $candidate ) use ( $selected_amount ) {
			return abs( $candidate['amount'] - $selected_amount ) < 0.005;
		} ) );

		if ( 1 !== count( $matches ) ) {
			return new WP_Error( 'fdm_ambiguous_price', __( 'The selected price is no longer available. Please choose it again.', 'food-and-drink-menu' ) );
		}

		return reset( $matches );
	}

	private function resolve_options( $item_id, $selected_options ) {
		$definitions = get_post_meta( $item_id, '_fdm_ordering_options', true );
		$definitions = is_array( $definitions ) ? $definitions : array();
		$resolved = array();

		$selected_options = array_filter( (array) $selected_options, 'is_scalar' );
		foreach ( array_values( array_unique( array_map( 'strval', $selected_options ) ) ) as $selected_id ) {
			if ( ! array_key_exists( $selected_id, $definitions ) && ! array_key_exists( (int) $selected_id, $definitions ) ) {
				return new WP_Error( 'fdm_invalid_option', __( 'One of the selected options is no longer available.', 'food-and-drink-menu' ) );
			}

			$definition = array_key_exists( $selected_id, $definitions ) ? $definitions[ $selected_id ] : $definitions[ (int) $selected_id ];
			$cost = self::normalize_money( isset( $definition['cost'] ) ? $definition['cost'] : 0 );
			if ( null === $cost ) {
				return new WP_Error( 'fdm_invalid_option_cost', __( 'One of the selected options has an invalid price.', 'food-and-drink-menu' ) );
			}

			$resolved[] = array(
				'id'   => $selected_id,
				'name' => isset( $definition['name'] ) ? sanitize_text_field( $definition['name'] ) : '',
				'cost' => $cost,
			);
		}

		return $resolved;
	}
}
}
