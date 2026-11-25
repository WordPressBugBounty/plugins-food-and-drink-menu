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
		global $fdm_controller;
		if ( ! $fdm_controller->permissions->can_execute( 'ordering' ) ) { return new WP_Error( 'fdm_ordering_suspended', __( 'Ordering is currently unavailable.', 'food-and-drink-menu' ) ); }
		return fdmOrderPricingWorker::resolve_item( $this, $item );
	}

	/**
	 * Revalidate every item before an order is inserted.
	 *
	 * @param array $items Cart items.
	 * @return array|WP_Error
	 */
	public function revalidate_cart_items( $items ) {
		global $fdm_controller;
		if ( ! $fdm_controller->permissions->can_execute( 'ordering' ) ) { return new WP_Error( 'fdm_ordering_suspended', __( 'Ordering is currently unavailable.', 'food-and-drink-menu' ) ); }
		return fdmOrderPricingWorker::revalidate_cart_items( $this, $items );
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

	public function resolve_options( $item_id, $selected_options ) {
		global $fdm_controller;
		if ( $selected_options && ! $fdm_controller->permissions->can_execute( 'order_advanced' ) ) {
			return new WP_Error( 'fdm_advanced_options_unavailable', __( 'Advanced ordering options are unavailable.', 'food-and-drink-menu' ) );
		}
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
