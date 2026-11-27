<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/** One-time, site-wide Black Friday 2026 promotion. */
class fdmBlackFriday2026Notice {
	// EST (UTC-5), independent of the WordPress site's configured timezone.
	const START = 1795669260; // November 26, 2026 at 00:01:00 EST.
	const END = 1796101200;   // December 1, 2026 at 00:00:00 EST (exclusive).
	const HIDDEN = 'fdm-bf2026-hidden';
	const SNOOZED = 'fdm-bf2026-snoozed';
	const ACTION = 'fdm_bf2026_dismiss';

	public function __construct() {
		add_action( 'admin_notices', array( $this, 'render' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'dismiss' ) );
	}

	protected function now() {
		return time();
	}

	private function in_window() {
		$now = $this->now();
		return $now >= self::START && $now < self::END;
	}

	private function audience() {
		global $fdm_controller;
		if ( empty( $fdm_controller->permissions ) || ! $fdm_controller->permissions->has_compatible_helper_contract( 2 ) ) return 'free';
		$contract = fspph_fdm_get_entitlement_contract( 2 );
		if ( ! empty( $contract['ultimate']['active'] ) ) return 'ultimate';
		// Trial activity alone is not evidence of a paid Premium customer.
		if ( ! empty( $contract['premium']['active'] ) && 'trial' !== $contract['premium']['status'] && ! empty( $contract['premium']['masked_key'] ) ) return 'premium';
		return 'free';
	}

	private function should_show() {
		return $this->in_window() && current_user_can( 'manage_options' )
			&& false === get_transient( self::HIDDEN ) && false === get_transient( self::SNOOZED )
			&& 'ultimate' !== $this->audience();
	}

	public function enqueue_assets() {
		if ( ! $this->should_show() ) return;
		wp_enqueue_style( 'fdm-bf2026-notice', FDM_PLUGIN_URL . '/assets/css/black-friday-2026-notice.css', array(), FDM_VERSION );
		wp_enqueue_script( 'fdm-bf2026-notice', FDM_PLUGIN_URL . '/assets/js/black-friday-2026-notice.js', array( 'jquery' ), FDM_VERSION, true );
		wp_localize_script( 'fdm-bf2026-notice', 'fdm_bf2026_notice', array(
			'url' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( self::ACTION ),
			'action' => self::ACTION,
			'error' => __( 'Could not hide this notice. Please try again.', 'food-and-drink-menu' ),
		) );
	}

	public function render() {
		if ( ! $this->should_show() ) return;
		?>
		<div class="notice notice-info fdm-bf2026-notice">
			<img class="fdm-bf2026-icon" src="<?php echo esc_url( FDM_PLUGIN_URL . '/assets/img/fdm-icon.png' ); ?>" width="100" height="100" alt="">
			<div class="fdm-bf2026-content">
				<?php if ( 'premium' === $this->audience() ) : ?>
					<p><?php esc_html_e( 'Take advantage of our Black Friday sale to upgrade to Restaurant Menu Ultimate! As a thank you for being a premium user, get your first year of Ultimate for only $130 or €130! Sale ends November 30th 11:59:59 PM EST.', 'food-and-drink-menu' ); ?></p>
					<p class="fdm-bf2026-actions">
						<a class="button button-primary" href="https://www.paypal.com/webapps/billing/plans/subscribe?plan_id=P-40S1754348523761LNMC7IPY" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Upgrade (EUR)', 'food-and-drink-menu' ); ?></a>
						<a class="button button-primary" href="https://www.paypal.com/webapps/billing/plans/subscribe?plan_id=P-8H793289WY3296336MRSSZ4I" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Upgrade (USD)', 'food-and-drink-menu' ); ?></a>
					</p>
				<?php else : ?>
					<p><?php esc_html_e( "Take advantage of our Black Friday sale to upgrade to Restaurant Menu Ultimate or Premium! Use code 'BF2026' at checkout to get 20% off of the regular price.  Sale ends November 30th at 11:59:59 PM, EST.", 'food-and-drink-menu' ); ?></p>
					<p><a class="button button-primary" href="https://www.fivestarplugins.com/plugins/five-star-restaurant-menu/?utm_source=plugin_admin_bf2026" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View Restaurant Menu Plans', 'food-and-drink-menu' ); ?></a></p>
				<?php endif; ?>
				<p class="fdm-bf2026-actions">
					<button type="button" class="button fdm-bf2026-dismiss" data-dismissal="temporary"><?php esc_html_e( 'Hide Temporarily', 'food-and-drink-menu' ); ?></button>
					<button type="button" class="button fdm-bf2026-dismiss" data-dismissal="permanent"><?php esc_html_e( "Don't Show Again", 'food-and-drink-menu' ); ?></button>
					<span class="fdm-bf2026-status" role="status" aria-live="polite"></span>
				</p>
			</div>
		</div>
		<?php
	}

	public function dismiss() {
		if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( self::ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not authorized to hide this notice.', 'food-and-drink-menu' ) ), 403 );
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Fixed values are checked below, after type validation and nonce verification.
		$mode = isset( $_POST['dismissal'] ) && is_string( $_POST['dismissal'] ) ? wp_unslash( $_POST['dismissal'] ) : '';
		if ( ! in_array( $mode, array( 'temporary', 'permanent' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid dismissal choice.', 'food-and-drink-menu' ) ), 400 );
		}
		$now = $this->now();
		if ( $now >= self::START && $now < self::END ) {
			$remaining = self::END - $now;
			$key = 'permanent' === $mode ? self::HIDDEN : self::SNOOZED;
			$ttl = 'permanent' === $mode ? $remaining : min( HOUR_IN_SECONDS, $remaining );
			// Separate keys ensure a stale tab's snooze cannot undo Don't Show Again.
			if ( ! set_transient( $key, 1, $ttl ) && false === get_transient( $key ) ) {
				wp_send_json_error( array( 'message' => __( 'Could not hide this notice. Please try again.', 'food-and-drink-menu' ) ), 500 );
			}
		}
		wp_send_json_success();
	}
}
