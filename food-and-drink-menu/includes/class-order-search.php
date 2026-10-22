<?php
/** Additive contact projection; order_data remains the authoritative record. */
if ( ! defined( 'ABSPATH' ) ) exit;

class fdmOrderSearch {
	const READY_OPTION = 'fdm-order-search-ready-v1';
	const CRON = 'fdm_order_search_backfill';
	const SOURCE_META = '_fdm_search_source_v1';
	const BATCH = 100;
	private $deleting = array();

	public function __construct() {
		foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $hook ) add_action( $hook, array( $this, 'metadata_changed' ), 10, 4 );
		add_action( 'save_post_' . FDM_ORDER_POST_TYPE, array( $this, 'sync' ), 20 );
		add_action( 'init', array( $this, 'maybe_schedule' ), 20 );
		add_action( self::CRON, array( $this, 'backfill' ) );
		add_action( 'before_delete_post', array( $this, 'before_delete' ) );
		add_action( 'deleted_post', array( $this, 'after_delete' ) );
	}

	public function before_delete( $post_id ) {
		if ( FDM_ORDER_POST_TYPE !== get_post_type( $post_id ) ) return;
		$this->deleting[ $post_id ] = true;
		update_option( 'fdm-search-deleting-' . $post_id, time() + 300, false );
	}
	public function after_delete( $post_id ) {
		if ( ! isset( $this->deleting[ $post_id ] ) ) return;
		$this->purge( $post_id );
		delete_option( 'fdm-search-deleting-' . $post_id );
		unset( $this->deleting[ $post_id ] );
	}
	private function delete_pending( $post_id ) {
		global $wpdb;
		$expiry = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'fdm-search-deleting-' . $post_id ) );
		return $wpdb->last_error || (int) $expiry > time();
	}
	private function purge( $post_id ) {
		foreach ( array( '_fdm_search_email', '_fdm_search_phone', '_fdm_search_name', self::SOURCE_META ) as $key ) delete_post_meta( $post_id, $key );
	}

	public function metadata_changed( $meta_id, $post_id, $key, $value ) {
		if ( 'order_data' === $key ) $this->sync( $post_id );
	}

	/** Read the actual stored bytes, not a stale object-cache snapshot. */
	public function sync( $post_id ) {
		global $wpdb;
		if ( isset( $this->deleting[ $post_id ] ) || FDM_ORDER_POST_TYPE !== get_post_type( $post_id ) || $this->delete_pending( $post_id ) ) return;
		$raw = (string) $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s ORDER BY meta_id LIMIT 1", $post_id, 'order_data' ) );
		$data = maybe_unserialize( $raw );
		foreach ( array( 'email', 'phone', 'name' ) as $field ) {
			$value = is_array( $data ) && is_scalar( $data[ $field ] ?? null ) ? (string) $data[ $field ] : '';
			update_post_meta( $post_id, '_fdm_search_' . $field, wp_slash( $value ) );
			if ( $value !== get_post_meta( $post_id, '_fdm_search_' . $field, true ) ) {
				delete_post_meta( $post_id, self::SOURCE_META );
				update_option( self::READY_OPTION, 0, false );
				$this->maybe_schedule();
				return;
			}
		}
		// Written last. A concurrent save cannot silently make stale projections
		// searchable: readiness checks compare this digest with current DB bytes.
		update_post_meta( $post_id, self::SOURCE_META, md5( $raw ) );
		// Check the cross-worker deletion marker first, then the uncached post
		// row. A delete can finish (and clear its marker) while indexing writes.
		if ( $this->delete_pending( $post_id ) || FDM_ORDER_POST_TYPE !== $wpdb->get_var( $wpdb->prepare( "SELECT post_type FROM {$wpdb->posts} WHERE ID = %d", $post_id ) ) ) $this->purge( $post_id );
	}

	private function pending_ids( $limit ) {
		global $wpdb;
		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT p.ID FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} d ON d.post_id = p.ID AND d.meta_key = %s
			LEFT JOIN {$wpdb->postmeta} i ON i.post_id = p.ID AND i.meta_key = %s
			WHERE p.post_type = %s AND (i.meta_id IS NULL OR i.meta_value <> MD5(COALESCE(d.meta_value, '')))
			ORDER BY p.ID ASC LIMIT %d", 'order_data', self::SOURCE_META, FDM_ORDER_POST_TYPE, $limit
		) );
		return $wpdb->last_error ? null : $ids;
	}

	public function is_ready() {
		$ids = $this->pending_ids( 1 );
		if ( null === $ids || $ids ) {
			update_option( self::READY_OPTION, 0, false );
			$this->maybe_schedule();
			return false;
		}
		return true;
	}

	public function maybe_schedule() {
		if ( 1 !== (int) get_option( self::READY_OPTION, 0 ) && ! wp_next_scheduled( self::CRON ) ) wp_schedule_single_event( time() + 60, self::CRON );
	}

	/** At most 100 legacy metadata records per cron run; no order-item hydration. */
	public function backfill() {
		$ids = $this->pending_ids( self::BATCH );
		if ( null === $ids ) { update_option( self::READY_OPTION, 0, false ); $this->maybe_schedule(); return 0; }
		foreach ( $ids as $id ) $this->sync( (int) $id );
		$pending = $this->pending_ids( 1 );
		$ready = null !== $pending && ! $pending;
		update_option( self::READY_OPTION, $ready ? 1 : 0, false );
		if ( ! $ready ) $this->maybe_schedule();
		return count( $ids );
	}
}
