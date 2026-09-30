<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'fdmBackwardsCompatibility' ) ) {
/** Versioned, idempotent compatibility migrations. */
class fdmBackwardsCompatibility {
	const NOTIFICATION_MIGRATION_VERSION = 1;
	const NOTIFICATION_MIGRATION_OPTION = 'fdm-notification-migration-version';
	const NOTIFICATION_REVIEW_OPTION = 'fdm-notification-migration-review';

	public function __construct() {
		add_action( 'init', array( $this, 'convert_notifications_to_table' ), 20 );
	}

	public function convert_notifications_to_table() {
		global $fdm_controller;
		if ( (int) get_option( self::NOTIFICATION_MIGRATION_OPTION, 0 ) >= self::NOTIFICATION_MIGRATION_VERSION ) return;

		$legacy = $this->get_legacy_notifications();
		foreach ( $legacy as $notification ) {
			if ( '' === trim( (string) $notification['subject'] ) || '' === trim( (string) $notification['message'] ) ) {
				update_option( self::NOTIFICATION_REVIEW_OPTION, array( 'version' => self::NOTIFICATION_MIGRATION_VERSION, 'reason' => 'missing_notification_content', 'recorded_at' => time() ), false );
				return;
			}
		}
		$stored = fdm_decode_infinite_table_setting( $fdm_controller->settings->get_setting( 'order-notifications' ) );
		$stored = is_array( $stored ) ? array_map( array( $this, 'normalize_notification' ), $stored ) : array();

		if ( ! $stored ) {
			$fdm_controller->settings->set_setting( 'order-notifications', wp_json_encode( $legacy ) );
			$fdm_controller->settings->save_settings();
			delete_option( self::NOTIFICATION_REVIEW_OPTION );
			update_option( self::NOTIFICATION_MIGRATION_OPTION, self::NOTIFICATION_MIGRATION_VERSION, false );
			return;
		}

		$expected_customer = $legacy[1];
		$defective_customer = $expected_customer;
		$defective_customer['target'] = 'admin';
		$correct_matches = array();
		$defect_matches = array();
		foreach ( $stored as $index => $notification ) {
			if ( $this->notifications_equal( $notification, $expected_customer ) ) $correct_matches[] = $index;
			if ( $this->notifications_equal( $notification, $defective_customer ) ) $defect_matches[] = $index;
		}

		if ( 1 === count( $defect_matches ) && 0 === count( $correct_matches ) ) {
			$stored[ $defect_matches[0] ]['target'] = 'customer';
			$fdm_controller->settings->set_setting( 'order-notifications', wp_json_encode( $stored ) );
			$fdm_controller->settings->save_settings();
			delete_option( self::NOTIFICATION_REVIEW_OPTION );
			update_option( self::NOTIFICATION_MIGRATION_OPTION, self::NOTIFICATION_MIGRATION_VERSION, false );
			return;
		}

		if ( count( $defect_matches ) > 1 || ( $defect_matches && $correct_matches ) ) {
			update_option( self::NOTIFICATION_REVIEW_OPTION, array( 'version' => self::NOTIFICATION_MIGRATION_VERSION, 'reason' => 'ambiguous_customer_target', 'recorded_at' => time() ), false );
			return;
		}

		delete_option( self::NOTIFICATION_REVIEW_OPTION );
		update_option( self::NOTIFICATION_MIGRATION_OPTION, self::NOTIFICATION_MIGRATION_VERSION, false );
	}

	private function get_legacy_notifications() {
		global $fdm_controller;
		return array(
			array(
				'enabled' => true, 'status' => 'fdm_order_received', 'type' => 'email', 'target' => 'admin',
				'subject' => $fdm_controller->settings->get_setting( 'admin-email-subject' ),
				'message' => $fdm_controller->settings->get_setting( 'admin-email-template' ),
			),
			array(
				'enabled' => 'none' !== $fdm_controller->settings->get_setting( 'customer-notification-type' ),
				'status' => 'fdm_order_accepted',
				'type' => 'sms' === $fdm_controller->settings->get_setting( 'customer-notification-type' ) ? 'sms' : 'email',
				'target' => 'customer',
				'subject' => $fdm_controller->settings->get_setting( 'customer-email-subject' ),
				'message' => $fdm_controller->settings->get_setting( 'customer-email-template' ),
			),
		);
	}

	public function normalize_notification( $notification ) {
		$notification = (array) $notification;
		return array(
			'enabled' => ! empty( $notification['enabled'] ),
			'status' => isset( $notification['status'] ) ? (string) $notification['status'] : '',
			'type' => isset( $notification['type'] ) ? (string) $notification['type'] : '',
			'target' => isset( $notification['target'] ) ? (string) $notification['target'] : '',
			'subject' => isset( $notification['subject'] ) ? html_entity_decode( (string) $notification['subject'], ENT_QUOTES, 'UTF-8' ) : '',
			'message' => isset( $notification['message'] ) ? html_entity_decode( (string) $notification['message'], ENT_QUOTES, 'UTF-8' ) : '',
		);
	}

	private function notifications_equal( $left, $right ) {
		return $this->normalize_notification( $left ) === $this->normalize_notification( $right );
	}
}
}
