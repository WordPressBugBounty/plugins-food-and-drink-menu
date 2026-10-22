<?php
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'fdmOrderNotificationSMS' ) ) {
/**
 * Class for order notification SMS messages that need to go out from the plugin
 *
 * @since 2.4.0
 */
class fdmOrderNotificationSMS extends fdmOrderNotification {

	/**
	 * Recipient phone number
	 * @since 2.4.0
	 */
	public $phone_number;

	/**
	 * Text message body
	 * @since 2.4.0
	 */
	public $message;

	/**
	 * The license key received for RTB Ultimate
	 * @since 2.4.0
	 */
	public $license_key;

	/**
	 * Email used for purchase, to validate message sending
	 * @since 2.4.0
	 */
	public $purchase_email;

	/**
	 * Prepare and validate notification data
	 *
	 * @return boolean if the data is valid and ready for transport
	 * @since 2.4.0
	 */
	public function prepare_notification() {

		$this->set_phone_number();
		$this->set_message();
		$this->set_license_key();
		$this->set_purchase_email();

		// Return false if we're missing any of the required information
		if ( 	empty( $this->phone_number) ||
				empty( $this->message) ||
				empty( $this->license_key) ||
				empty( $this->purchase_email)  ) {
			return false;
		}

		return true;
	}

	/**
	 * Set phone number
	 * @since 2.4.0
	 */
	public function set_phone_number() {
		global $fdm_controller;

		if ( $this->target == 'admin' ) { $phone_number = $fdm_controller->settings->get_setting( 'admin-sms-phone-number' ); }
		else { $phone_number = $this->order->phone; }

		$this->phone_number = apply_filters( 'fdm_notification_sms_phone_number', $phone_number, $this );

	}

	/**
	 * Set text message body
	 * @since 2.4.0
	 */
	public function set_message() {
		global $fdm_controller;

		$this->message = empty( $this->message ) ? '' : $this->message;

		$this->message = apply_filters( 'fdm_notification_sms_template', $this->process_template( strip_tags( $this->message ) ), $this );

	}

	/**
	 * Set license key
	 * @since 2.4.0
	 */
	public function set_license_key() {

		$this->license_key = function_exists( 'fspph_fdm_ultimate_service_key' ) ? fspph_fdm_ultimate_service_key() : '';

	}

	/**
	 * Set purchase email
	 * @since 2.4.0
	 */
	public function set_purchase_email() {
		global $fdm_controller;

		$this->purchase_email = $fdm_controller->settings->get_setting( 'ultimate-purchase-email' );

	}

	/**
	 * Send notification
	 * @since 2.4.0
	 */
	public function send_notification() {
		global $fdm_controller;

		$endpoint = apply_filters( 'fdm_sms_service_endpoint', 'https://www.fivestarplugins.com/sms-handling/v2/fdm-send.php' );
		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 10,
				'body'    => array(
					'api_version'  => 2,
					'license_key'  => $this->license_key,
					'admin_email'  => $this->purchase_email,
					'plugin'       => 'fdm',
					'phone_number' => $this->phone_number,
					'message'      => $this->message,
					'country_code' => $fdm_controller->settings->get_setting( 'fdm-country-code' ),
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) return false;
		$return = json_decode( wp_remote_retrieve_body( $response ) );
		if ( ! is_object( $return ) || 2 !== (int) ( isset( $return->api_version ) ? $return->api_version : 0 ) ) return false;
		$return->success = ! empty( $return->success );

		if ( $return->success ) {

			$transient = array(
    		  'expiry'  => $return->expiry,
    		  'balance' => $return->balance,
    		);

    		set_transient( 'fdm-credit-information', $transient, 3600 * 24 * 7 );
		}

		return $return->success;
	}
}
}

?>
