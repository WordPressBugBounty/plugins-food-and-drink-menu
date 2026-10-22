<?php
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'fdmOrderManager' ) ) {
/**
 * Class to handle the orders made through the plugin
 *
 * @since 2.1.0
 */
class fdmOrderManager {

	// whether or not the restaurant is accepting orders
	public $is_open_for_ordering;

	public function __construct() {

		add_action( 'admin_init', array( $this, 'maybe_delete_old_orders' ) );
		add_action( 'fdm_cleanup_old_orders', array( $this, 'maybe_delete_old_orders' ) );

		add_action( 'transition_post_status', array( $this, 'handle_order_post_status_notifications' ), 10, 3 );
		add_action( 'fdm_reconcile_order_transition', array( $this, 'reconcile_transition' ) );
		add_action( 'deleted_post', array( $this, 'schedule_notice_cleanup' ), 10, 2 );
		add_action( 'fdm_cleanup_order_notices', array( $this, 'cleanup_order_notices' ) );
	}

	public function schedule_notice_cleanup( $id, $post ) {
		if ( FDM_ORDER_POST_TYPE === $post->post_type ) wp_schedule_single_event( time() + 1, 'fdm_cleanup_order_notices', array( $id ) );
	}

	public function cleanup_order_notices( $id ) {
		global $wpdb;
		$id = absint( $id );
		if ( ! $id || get_post( $id ) ) return;
		$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT 100", $wpdb->esc_like( 'fdm-order-notice-' . $id . '-' ) . '%' ) );
		if ( 100 === (int) $deleted ) wp_schedule_single_event( time() + 60, 'fdm_cleanup_order_notices', array( $id ) );
	}

	/** Staff transitions never perform payment or change order snapshots. */
	public function transition_order_status( $id, $status, $args = array() ) {
		global $fdm_controller;
		$id = absint( $id );
		$args = wp_parse_args( $args, array( 'expected_status' => null, 'revision' => null, 'request_id' => '', 'source' => 'admin', 'api_key' => '', 'correction_reason' => '' ) );
		$api = 'api' === $args['source'];
		$authorized = current_user_can( 'manage_fdm_orders' );
		if ( $api ) {
			global $fsp_premium_helper;
			$authorized = ! empty( $fsp_premium_helper->apis ) && $fsp_premium_helper->apis->verify_write_api_key_access( $args['api_key'] )
				&& $fdm_controller->permissions->check_permission( 'api_usage' );
		}
		if ( ! $authorized || ! $fdm_controller->permissions->check_permission( 'ordering' ) ) return new WP_Error( 'fdm_status_forbidden', __( 'Order updates are unavailable for this account.', 'food-and-drink-menu' ), array( 'status' => 403 ) );
		if ( ! is_string( $args['request_id'] ) || strlen( $args['request_id'] ) > 80 || ( $args['request_id'] && ! preg_match( '/^[a-zA-Z0-9_-]+$/D', $args['request_id'] ) ) ) return new WP_Error( 'fdm_invalid_request_id', __( 'Invalid update identifier.', 'food-and-drink-menu' ), array( 'status' => 400 ) );
		$args['request_id'] = $args['request_id'] ?: wp_generate_uuid4();
		$owner = $this->acquire_transition_lock( $id );
		if ( ! $owner ) return new WP_Error( 'fdm_order_busy', __( 'This order is being updated. Reload and try again.', 'food-and-drink-menu' ), array( 'status' => 409 ) );
		try {
			$this->reconcile_transition( $id, true );
			clean_post_cache( $id );
			$post = get_post( $id );
			if ( ! $this->is_valid_order_post_object( $post ) ) return new WP_Error( 'fdm_invalid_order', __( 'Invalid order.', 'food-and-drink-menu' ), array( 'status' => 404 ) );
			$revision = (int) get_post_meta( $id, '_fdm_order_status_revision', true );
			$history = (array) get_post_meta( $id, '_fdm_order_transition_history', true );
			foreach ( $history as $entry ) {
				if ( is_array( $entry ) && ( $entry['request_id'] ?? '' ) === $args['request_id'] ) {
					if ( $entry['to'] !== $status ) return $this->transition_conflict( $post, $revision );
					return array( 'status_key' => $post->post_status, 'revision' => $revision, 'changed' => false );
				}
			}
			// Old app callers are re-read under the lock; they still obey the forward graph.
			if ( ( null !== $args['expected_status'] && $post->post_status !== $args['expected_status'] )
				|| ( null !== $args['revision'] && $revision !== (int) $args['revision'] ) ) return $this->transition_conflict( $post, $revision );
			$graph = array( 'fdm_order_received' => 'fdm_order_accepted', 'fdm_order_accepted' => 'fdm_order_preparing', 'fdm_order_preparing' => 'fdm_order_ready', 'fdm_order_ready' => '' );
			if ( ! isset( $graph[ $post->post_status ], $graph[ $status ] ) ) return new WP_Error( 'fdm_invalid_order_status', __( 'Only received, paid orders can be advanced.', 'food-and-drink-menu' ), array( 'status' => 400 ) );
			if ( $post->post_status === $status ) return array( 'status_key' => $status, 'revision' => $revision, 'changed' => false );
			$correction = sanitize_text_field( $args['correction_reason'] );
			$keys = array_keys( $graph );
			$backwards = array_search( $status, $keys, true ) < array_search( $post->post_status, $keys, true );
			if ( $graph[ $post->post_status ] !== $status && ( $api || ! $backwards || '' === $correction ) ) return new WP_Error( 'fdm_invalid_transition', __( 'Advance one step, or use an explicit staff correction with a reason.', 'food-and-drink-menu' ), array( 'status' => 400 ) );
			$journal = array( 'request_id' => $args['request_id'], 'from' => $post->post_status, 'to' => $status, 'revision' => $revision + 1,
				'actor' => $api ? 0 : get_current_user_id(), 'source' => $api ? 'api' : 'admin', 'at' => time(), 'correction_reason' => substr( $correction, 0, 200 ) );
			if ( ! $this->owns_transition_lock( $id, $owner ) ) return $this->transition_conflict( $post, $revision );
			update_post_meta( $id, '_fdm_order_transition_pending', $journal );
			wp_schedule_single_event( time() + 95, 'fdm_reconcile_order_transition', array( $id ) );
			$result = wp_update_post( array( 'ID' => $id, 'post_status' => $status ), true );
			// A slow third-party transition listener may outlive the lease. The
			// next owner/cron repairs the journal; this process must not touch it.
			if ( ! $this->owns_transition_lock( $id, $owner ) ) {
				clean_post_cache( $id );
				return $this->transition_conflict( get_post( $id ), (int) get_post_meta( $id, '_fdm_order_status_revision', true ) );
			}
			if ( is_wp_error( $result ) ) { delete_post_meta( $id, '_fdm_order_transition_pending' ); return $result; }
			$this->reconcile_transition( $id, true );
			do_action( 'fdm_order_transition_committed', $id, $journal );
			return array( 'status_key' => $status, 'revision' => $revision + 1, 'changed' => true );
		} finally { $this->release_transition_lock( $id, $owner ); }
	}

	private function transition_conflict( $post, $revision ) {
		return new WP_Error( 'fdm_status_conflict', __( 'This order changed since you opened it. Reload before updating.', 'food-and-drink-menu' ), array( 'status' => 409, 'status_key' => $post->post_status, 'revision' => $revision ) );
	}

	private function acquire_transition_lock( $id ) {
		global $wpdb;
		$name = 'fdm-order-transition-lock-' . $id;
		$owner = array( 'token' => wp_generate_uuid4(), 'expires_at' => time() + 90 );
		if ( add_option( $name, $owner, '', false ) ) return $owner;
		$stored = get_option( $name );
		if ( is_array( $stored ) && (int) ( $stored['expires_at'] ?? 0 ) < time() ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $name, maybe_serialize( $stored ) ) );
			wp_cache_delete( $name, 'options' );
			if ( add_option( $name, $owner, '', false ) ) return $owner;
		}
		return false;
	}

	private function release_transition_lock( $id, $owner ) {
		global $wpdb;
		$name = 'fdm-order-transition-lock-' . $id;
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $name, maybe_serialize( $owner ) ) );
		wp_cache_delete( $name, 'options' );
	}

	private function owns_transition_lock( $id, $owner ) {
		global $wpdb;
		return (int) $owner['expires_at'] > time() && maybe_serialize( $owner ) === $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'fdm-order-transition-lock-' . $id ) );
	}

	/** Repair committed metadata only: never replay hooks, payments or notifications. */
	public function reconcile_transition( $id, $locked = false ) {
		$owner = $locked ? true : $this->acquire_transition_lock( $id );
		if ( ! $owner ) return;
		try {
			$journal = get_post_meta( $id, '_fdm_order_transition_pending', true );
			if ( ! is_array( $journal ) || empty( $journal['request_id'] ) ) return;
			clean_post_cache( $id );
			if ( get_post_status( $id ) === $journal['to'] ) {
				$history = get_post_meta( $id, '_fdm_order_transition_history', true );
				$history = is_array( $history ) ? $history : array();
				if ( ! in_array( $journal['request_id'], array_column( $history, 'request_id' ), true ) ) $history[] = $journal;
				update_post_meta( $id, '_fdm_order_transition_history', array_slice( $history, -50 ) );
				update_post_meta( $id, '_fdm_order_status_revision', max( (int) get_post_meta( $id, '_fdm_order_status_revision', true ), $journal['revision'] ) );
			}
			delete_post_meta( $id, '_fdm_order_transition_pending' );
		} finally { if ( ! $locked ) $this->release_transition_lock( $id, $owner ); }
	}

	/** One local attempt per committed transition/configuration; remote delivery is not exactly-once. */
	public function reserve_notification( $id, $status, $notification_id ) {
		$journal = get_post_meta( $id, '_fdm_order_transition_pending', true );
		$request = is_array( $journal ) && ( $journal['to'] ?? '' ) === $status ? $journal['request_id'] : 'initial-' . $status;
		$name = 'fdm-order-notice-' . $id . '-' . hash( 'sha256', $request . '|' . $status . '|' . $notification_id );
		return add_option( $name, time(), '', false );
	}

	public function maybe_delete_old_orders() {
		global $fdm_controller;
		if ( ! $fdm_controller->permissions->check_permission( 'ordering' ) ) return;

		if ( get_option( 'fdm-delete-order-check' ) + 3600 > time() ) { return; }

		$order_statuses = fdm_get_order_statuses();

		$args = array( 
			'posts_per_page' => 100,
			'fields'          => 'ids',
			'no_found_rows'   => true,
			'post_type' 	=> FDM_ORDER_POST_TYPE,
			'post_status'	=> array_keys( $order_statuses ),
			'date_query' 	=> array(
				array(
					'before' => wp_date( 'Y-m-d H:i:s', (int) ( time() - $fdm_controller->settings->get_setting( 'fdm-ordering-order-delete-time' ) * DAY_IN_SECONDS ) )
				)
			)
		);

		$query = new WP_Query( $args );
		$delete_posts = $query->posts;

		foreach ( $delete_posts as $delete_post_id ) {
			$owner = $this->acquire_transition_lock( $delete_post_id );
			if ( ! $owner ) continue;
			try { $this->reconcile_transition( $delete_post_id, true ); wp_delete_post( $delete_post_id, true ); }
			finally { $this->release_transition_lock( $delete_post_id, $owner ); }
		}

		if ( count( $delete_posts ) < 100 ) update_option( 'fdm-delete-order-check', time() );
		elseif ( ! wp_next_scheduled( 'fdm_cleanup_old_orders' ) ) wp_schedule_single_event( time() + 60, 'fdm_cleanup_old_orders' );
	}

	public function submit_order( $args ) {
		global $fdm_controller;
		if ( ! $fdm_controller->permissions->check_permission( 'ordering' ) || ! $fdm_controller->settings->get_setting( 'fdm-enable-ordering' ) ) return new WP_Error( 'fdm_ordering_suspended', __( 'Ordering is currently unavailable.', 'food-and-drink-menu' ) );
		$delivery_mode = isset( $args['delivery'] ) ? $args['delivery'] : '';
		if ( isset( $args['permalink'] ) ) $args['permalink'] = remove_query_arg( 'fdm_table', $args['permalink'] );
		if ( ! in_array( $delivery_mode, array( 'pickup', 'delivery', 'dine_in' ), true ) ) {
			return new WP_Error( 'fdm_invalid_delivery_mode', __( 'Choose pickup or delivery.', 'food-and-drink-menu' ) );
		}
		if ( 'delivery' === $delivery_mode && ! $fdm_controller->settings->get_setting( 'fdm-ordering-enable-delivery' ) ) {
			return new WP_Error( 'fdm_delivery_unavailable', __( 'Delivery is not currently available.', 'food-and-drink-menu' ) );
		}

		$payment_path = isset( $args['payment_path'] ) ? $args['payment_path'] : '';
		$payment_enabled = (bool) $fdm_controller->settings->get_setting( 'enable-payment' );
		$payment_optional = (bool) $fdm_controller->settings->get_setting( 'payment-optional' );
		$valid_payment_path = ( ! $payment_enabled && 'none' === $payment_path )
			|| ( $payment_enabled && 'online' === $payment_path )
			|| ( $payment_enabled && $payment_optional && 'store' === $payment_path );
		if ( ! $valid_payment_path ) {
			return new WP_Error( 'fdm_invalid_payment_path', __( 'The selected payment method is not available.', 'food-and-drink-menu' ) );
		}

		if ( $fdm_controller->cart->get_validation_errors() ) {
			return new WP_Error( 'fdm_stale_cart', __( 'Your menu changed while you were ordering. Please review your cart and try again.', 'food-and-drink-menu' ) );
		}

		$pricing = new fdmOrderPricing();
		$order_items = $pricing->revalidate_cart_items( $fdm_controller->cart->get_cart_items() );
		if ( is_wp_error( $order_items ) ) { return $order_items; }
		unset( $args['table_id'], $args['table_label'], $args['fulfillment_mode'] );
		$args['fulfillment_mode'] = $delivery_mode;
		if ( 'dine_in' === $delivery_mode ) {
			$context = $fdm_controller->table_context->checkout_snapshot( $args['permalink'] ?? '', $order_items );
			if ( is_wp_error( $context ) ) return $context;
			$args = array_merge( $args, $context );
		}

		$args['order_items'] 			= $order_items;
		$args['edit_confirmation_code']	= $this->return_random_string();
		$args['pricing_version']		= fdmOrderPricing::SNAPSHOT_VERSION;
		$args['order_currency']		= strtoupper( sanitize_text_field( $fdm_controller->settings->get_setting( 'ordering-currency' ) ) );
		$args['pricing_snapshot_version'] = fdmOrderPricing::SNAPSHOT_VERSION;
		$args['currency']             = $args['order_currency'];
		$args['delivery']			= 'delivery' === $delivery_mode;

		$tip_amount = fdmOrderPricing::normalize_money( isset( $args['tip_amount'] ) ? $args['tip_amount'] : 0 );
		if ( null === $tip_amount ) {
			return new WP_Error( 'fdm_invalid_tip', __( 'Enter a valid non-negative tip amount.', 'food-and-drink-menu' ) );
		}
		$args['tip_amount'] = $tip_amount;

		$args['post_status'] = 'online' === $payment_path ? 'draft' : 'fdm_order_received';
		unset( $args['payment_path'] );
		$args['custom_field_snapshots'] = fdm_create_order_custom_field_snapshots( isset( $args['custom_fields'] ) ? $args['custom_fields'] : array() );
		$args['custom_fields'] = array();
		foreach ( $args['custom_field_snapshots'] as $snapshot ) $args['custom_fields'][ $snapshot['slug'] ] = $snapshot['value'];

		$order = new fdmOrderItem( $args );

		$order->set_payment_amounts();

		$saved = $order->save_order_post();
		if ( is_wp_error( $saved ) || empty( $order->id ) ) {
			return new WP_Error( 'fdm_order_insert_failed', __( 'The order could not be saved. Please try again.', 'food-and-drink-menu' ) );
		}

		$fdm_controller->cart->clear_cart();

		// Email will be triggerd by the action set on post_status transition

		$this->set_recent_order_cookie( $order->id );
		if ( 'dine_in' === $delivery_mode ) $fdm_controller->table_context->clear();

		return $order->id;
	}

	/**
	 * Handle any notifications that need to be sent on order status change
	 * @since 2.4.1
	 */
	public function handle_order_post_status_notifications( $new_status, $old_status, $post ) {
		global $fdm_controller;

		if ( $post->post_type != FDM_ORDER_POST_TYPE ) { return; }

		if ( $new_status == $old_status ) { return; }

		$order = new fdmOrderItem();

		$order->load( $post ); 

		$notifications = fdm_decode_infinite_table_setting( $fdm_controller->settings->get_setting( 'order-notifications' ) );

		foreach ( $notifications as $notification_id => $notification ) {

			if ( ! $notification->enabled ) { continue; }

			if ( $notification->status != $new_status ) { continue; }
			if ( 'sms' === $notification->type && ! $fdm_controller->permissions->check_permission( 'order_advanced' ) ) continue;
			if ( ! $this->reserve_notification( $post->ID, $new_status, (string) $notification_id ) ) continue;

			$args = array( 
				'order'		=> $order, 
				'target'	=> $notification->target,
			);
			
			$order_notification = $notification->type == 'sms' ? new fdmOrderNotificationSMS( $args ) : new fdmOrderNotificationEmail( $args );

			$order_notification->set( 'message', $notification->message );
			$order_notification->set( 'subject', $notification->subject );

			$order_notification->prepare_notification();
			$order_notification->send_notification();
		}
	}

	public function set_recent_order_cookie( $id ) {
		$payload = absint( $id ) . '.' . ( time() + 2 * HOUR_IN_SECONDS );
		$value = $payload . '.' . hash_hmac( 'sha256', home_url() . '|' . $payload, wp_salt( 'auth' ) );
		setcookie( 'fdm_recent_order', $value, array( 'expires' => time() + 2 * HOUR_IN_SECONDS, 'path' => '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
		$_COOKIE['fdm_recent_order'] = $value;
	}

	public function get_recent_order_id() {

		$value = wp_unslash( $_COOKIE['fdm_recent_order'] ?? '' );
		if ( ! is_string( $value ) || strlen( $value ) > 120 ) return 0;
		$parts = explode( '.', $value );
		if ( 3 !== count( $parts ) || ! ctype_digit( $parts[0] ) || ! ctype_digit( $parts[1] ) || (int) $parts[1] < time() ) return 0;
		$signature = hash_hmac( 'sha256', home_url() . '|' . $parts[0] . '.' . $parts[1], wp_salt( 'auth' ) );
		return hash_equals( $signature, $parts[2] ) ? absint( $parts[0] ) : 0;
	}

	/**
	 * Delete an order (or send to trash)
	 *
	 * @since 2.1.0
	 */
	public function delete_order( $id ) {

		$id = absint( $id );
		if ( ! current_user_can( 'manage_fdm_orders' ) ) {
			return false;
		}

		$order = get_post( $id );

		if ( ! $this->is_valid_order_post_object( $order ) ) {
			return false;
		}

		// If we're already looking at trashed posts, delete it for good.
		// Otherwise, just send it to trash.
		if ( !empty( $_GET['status'] ) && $_GET['status'] == 'trash' ) {
			$screen = get_current_screen();
			if ( $screen->base == 'menu-posts-fdm-menu' ) {
				$result = wp_delete_post( $id, true );
			}
		} else {
			$result = wp_trash_post( $id );
		}

		if ( $result === false ) {
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Update an order status.
	 * @since 2.1.0
	 */
	function update_order_status( $id, $status, $args = array() ) {
		if ( isset( $_POST['fdm_expected_status'][ $id ], $_POST['fdm_revision'][ $id ] ) ) {
			$args['expected_status'] = sanitize_key( wp_unslash( $_POST['fdm_expected_status'][ $id ] ) );
			$args['revision'] = absint( $_POST['fdm_revision'][ $id ] );
		}
		$result = $this->transition_order_status( $id, $status, $args );
		return is_wp_error( $result ) ? $result : ( $result['changed'] ? true : null );
	}

	/**
	 * Check if status is valid for orders
	 * @since 2.1.0
	 */
	public function is_valid_order_status( $status ) {
		$order_statuses = fdm_get_order_statuses();

		return isset( $order_statuses[ $status ] ) ? true : false;
	}

	/**
	 * Check if order is a valid Post object with the correct post type
	 * @since 2.1.0
	 */
	public function is_valid_order_post_object( $order ) {
		return ! is_wp_error( $order ) && is_object( $order ) && $order->post_type == FDM_ORDER_POST_TYPE;
	}

	public function is_open_for_ordering() {
		global $fdm_controller;

		if ( ! empty( $fdm_controller->settings->get_setting( 'fdm-ordering-pause-ordering' ) ) ) { return false; }

		$maximum = absint( $fdm_controller->settings->get_setting( 'fdm-ordering-maximum-received-orders' ) );
		$args = array(
			'post_type'			=> FDM_ORDER_POST_TYPE,
			'post_status'		=> 'fdm_order_received',
			'posts_per_page'	=> 1,
			'fields'			=> 'ids',
			'no_found_rows'		=> false,
		);

		$order_query = new WP_Query( $args );
		
		if ( $maximum && $order_query->found_posts >= $maximum ) { return false; }

		if ( empty( $this->is_open_for_ordering ) ) { $this->is_open_for_ordering = $this->determine_open_for_ordering(); }

		return $this->is_open_for_ordering;
	}

	public function determine_open_for_ordering() {
		global $fdm_controller;

		$schedule_open 	= is_array( $fdm_controller->settings->get_setting( 'schedule-open' ) ) ? $fdm_controller->settings->get_setting( 'schedule-open' ) : array();
		$exceptions 	= is_array( $fdm_controller->settings->get_setting( 'schedule-closed' ) ) ? $fdm_controller->settings->get_setting( 'schedule-closed' ) : array();

		if ( empty( $schedule_open ) ) { return true; }

		$timezone = wp_timezone(); 
		$gmt_seconds_offset = $timezone->getOffset( new DateTime );

		$today_weekday = strtolower( date( 'l', time() + $gmt_seconds_offset ) ); 
		$today_date = date( 'Y-m-d ', time() + $gmt_seconds_offset ); 

		$ordering_open = false;

		foreach ( $schedule_open as $rule ) {
			if ( !empty( $rule['weekdays'] ) ) {
				foreach ( $rule['weekdays'] as $weekday => $value ) { 
					
					if ( $weekday == $today_weekday and $value ) {
						if ( empty( $rule['time'] ) ) { $ordering_open = true; }						
						else { 
							if ( time() + $gmt_seconds_offset  > strtotime( $today_date . $rule['time']['start']) and time() + $gmt_seconds_offset  < strtotime( $today_date . $rule['time']['end'])) { $ordering_open = true; }
						}
					}

				}
			}
		}

		if ( ! is_array( $exceptions ) ) { return $ordering_open; }

		foreach ( $exceptions as $exception ) {
			if ( !empty( $exception['date'] ) and date('Y-m-d ', strtotime( $exception['date'] ) ) == $today_date ) {
				
				if ( empty( $exception['time'] ) ) { $ordering_open = false; }						
				else {
					if ( time() + $gmt_seconds_offset  > strtotime( $today_date . $exception['time']['start'] ) and time() + $gmt_seconds_offset  < strtotime( $today_date . $exception['time']['end'] ) ) { $ordering_open = true; }
				}
			}
		}

		return $ordering_open;
	}

	public function get_ordering_close_time() {
		global $fdm_controller;

		$schedule_open 	= is_array( $fdm_controller->settings->get_setting( 'schedule-open' ) ) ? $fdm_controller->settings->get_setting( 'schedule-open' ) : array();
		$exceptions 	= is_array( $fdm_controller->settings->get_setting( 'schedule-closed' ) ) ? $fdm_controller->settings->get_setting( 'schedule-closed' ) : array();

		if ( empty( $schedule_open ) ) { return 0; }

		$timezone = wp_timezone(); 
		$gmt_seconds_offset = $timezone->getOffset( new DateTime );

		$today_weekday = strtolower( date( 'l', time() + $gmt_seconds_offset ) ); 
		$today_date = date( 'Y-m-d ', time() + $gmt_seconds_offset ); 

		$closing_time = 0;

		foreach ( $schedule_open as $rule ) {
			if ( !empty( $rule['weekdays'] ) ) {
				foreach ( $rule['weekdays'] as $weekday => $value ) { 
					
					if ( $weekday == $today_weekday and $value ) {
						if ( empty( $rule['time'] ) ) { $closing_time = 24; }						
						else { 
							$closing_time = fdm_time_to_decimal( strtotime( $today_date . $rule['time']['end'] ) );
						}
					}

				}
			}
		}

		if ( ! is_array( $exceptions ) ) { return $closing_time; }

		foreach ( $exceptions as $exception ) {
			if ( !empty( $exception['date'] ) and date('Y-m-d ', strtotime( $exception['date'] ) ) == $today_date ) {
				
				if ( empty( $exception['time'] ) ) { $closing_time = 0; }						
				else {
					$closing_time = fdm_time_to_decimal( strtotime( $today_date . $exception['time']['end'] ) );
				}
			}
		}

		return $closing_time;
	}

	public function return_random_string( $length = 4 ) {
       
    	$random_string     = '';
    	$characters         = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    	$characters_length = strlen( $characters );
    
   		for ( $i = 0; $i < $length; $i++ ) {
    		$random_string .= substr( $characters, rand( 0, $characters_length ), 1 );
    	}
    
    	return $random_string;
    }
}
} // endif
