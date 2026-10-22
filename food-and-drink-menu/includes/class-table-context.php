<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/** Optional public table QR context; it does not authenticate physical presence. */
class fdmTableContext {
	const COOKIE = 'fdm_table_context';
	private $invalid_entry = false;

	public function __construct() {
		add_action( 'template_redirect', array( $this, 'receive_context' ), 1 );
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_post_fdm_save_tables', array( $this, 'save_tables' ) );
	}

	private function settings() { $value = get_option( 'food-and-drink-menu-settings', array() ); return is_array( $value ) ? $value : array(); }
	private function enabled() {
		global $fdm_controller;
		$settings = $this->settings();
		return ! empty( $settings['fdm-dine-in-enabled'] ) && ! empty( $settings['fdm-enable-ordering'] ) && $fdm_controller->permissions->check_permission( 'ordering' );
	}
	private function signature( $payload ) { return hash_hmac( 'sha256', home_url() . '|' . $payload, wp_salt( 'auth' ) ); }
	private function encode( $data ) { $payload = rtrim( strtr( base64_encode( wp_json_encode( $data ) ), '+/', '-_' ), '=' ); return $payload . '.' . $this->signature( $payload ); }
	private function decode( $value ) {
		if ( ! is_string( $value ) || strlen( $value ) > 1024 ) return false;
		$parts = explode( '.', $value );
		if ( 2 !== count( $parts ) || ! hash_equals( $this->signature( $parts[0] ), $parts[1] ) ) return false;
		$data = json_decode( base64_decode( strtr( $parts[0], '-_', '+/' ), true ), true );
		return is_array( $data ) ? $data : false;
	}

	public function table_url( $table ) {
		$token = $this->encode( array( 'v' => 1, 'id' => $table['id'], 'page' => (int) $table['page_id'], 'rotation' => $table['rotation'] ) );
		return add_query_arg( 'fdm_table', $token, get_permalink( $table['page_id'] ) );
	}

	public function validate_token( $token, $page_id = null ) {
		$data = $this->decode( $token );
		if ( ! $this->enabled() || ! $data || 1 !== ( $data['v'] ?? null ) ) return false;
		$settings = $this->settings();
		foreach ( (array) ( $settings['fdm-dine-in-tables'] ?? array() ) as $table ) {
			if ( ! is_array( $table ) || empty( $table['active'] ) || ( $data['id'] ?? '' ) !== $table['id']
				|| ( $data['rotation'] ?? '' ) !== $table['rotation'] || ( $data['page'] ?? 0 ) !== (int) $table['page_id'] ) continue;
			if ( null !== $page_id && (int) $page_id !== (int) $table['page_id'] ) return false;
			$page = get_post( $table['page_id'] );
			if ( ! $page || 'page' !== $page->post_type || 'publish' !== $page->post_status || ! $this->page_menu_ids( $page ) ) return false;
			return $table;
		}
		return false;
	}

	/** Only published menus explicitly embedded in the selected page qualify. */
	private function page_menu_ids( $page ) {
		$ids = array();
		preg_match_all( '/' . get_shortcode_regex( array( 'fdm-menu' ) ) . '/s', $page->post_content, $matches, PREG_SET_ORDER );
		foreach ( $matches as $match ) { if ( '[' === $match[1] && ']' === $match[6] ) continue; $atts = shortcode_parse_atts( $match[3] ); if ( is_array( $atts ) && ! empty( $atts['id'] ) ) $ids[] = absint( $atts['id'] ); }
		$walk = function( $blocks ) use ( &$walk, &$ids ) {
			foreach ( $blocks as $block ) {
				if ( 'food-and-drink-menu/menu' === $block['blockName'] && ! empty( $block['attrs']['id'] ) ) $ids[] = absint( $block['attrs']['id'] );
				if ( ! empty( $block['innerBlocks'] ) ) $walk( $block['innerBlocks'] );
			}
		};
		$walk( parse_blocks( $page->post_content ) );
		return array_values( array_filter( array_unique( $ids ), function( $id ) { $post = get_post( $id ); return $post && FDM_MENU_POST_TYPE === $post->post_type && 'publish' === $post->post_status; } ) );
	}

	public function receive_context() {
		if ( ! isset( $_GET['fdm_table'] ) ) return;
		$token = is_string( $_GET['fdm_table'] ) ? wp_unslash( $_GET['fdm_table'] ) : '';
		$table = $this->validate_token( $token, get_queried_object_id() );
		if ( ! $table || ! is_ssl() ) { $this->clear(); $this->invalid_entry = true; return; }
		$value = $this->encode( array( 'token' => $token, 'expires' => time() + 2 * HOUR_IN_SECONDS ) );
		setcookie( self::COOKIE, $value, array( 'expires' => time() + 2 * HOUR_IN_SECONDS, 'path' => COOKIEPATH ?: '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax' ) );
		$_COOKIE[ self::COOKIE ] = $value;
		wp_safe_redirect( get_permalink( $table['page_id'] ) );
		exit;
	}

	public function current() {
		if ( ! is_ssl() ) return false;
		$data = $this->decode( wp_unslash( $_COOKIE[ self::COOKIE ] ?? '' ) );
		return $data && (int) ( $data['expires'] ?? 0 ) > time() ? $this->validate_token( $data['token'] ?? '' ) : false;
	}

	public function checkout_snapshot( $permalink, $items ) {
		$table = $this->current();
		if ( ! $table || url_to_postid( $permalink ) !== (int) $table['page_id'] ) return new WP_Error( 'fdm_invalid_table_context', __( 'Table ordering is unavailable. Choose pickup or delivery.', 'food-and-drink-menu' ) );
		$sections = array();
		foreach ( $this->page_menu_ids( get_post( $table['page_id'] ) ) as $menu_id ) {
			foreach ( array( 'one', 'two' ) as $column ) $sections = array_merge( $sections, array_map( 'absint', explode( ',', get_post_meta( $menu_id, 'fdm_menu_column_' . $column, true ) ) ) );
		}
		$sections = array_values( array_filter( array_unique( $sections ) ) );
		if ( ! $sections || ! $items ) return new WP_Error( 'fdm_table_menu_empty', __( 'Review the menu linked to this table before ordering.', 'food-and-drink-menu' ) );
		foreach ( $items as $item ) if ( ! has_term( $sections, 'fdm-menu-section', $item->id ) ) return new WP_Error( 'fdm_table_menu_mismatch', __( 'Review the menu linked to this table before ordering.', 'food-and-drink-menu' ) );
		return array( 'fulfillment_mode' => 'dine_in', 'table_id' => $table['id'], 'table_label' => $table['label'] );
	}

	public function unavailable() { return $this->invalid_entry || ( isset( $_COOKIE[ self::COOKIE ] ) && ! $this->current() ); }
	public function clear() {
		setcookie( self::COOKIE, '', array( 'expires' => time() - 3600, 'path' => COOKIEPATH ?: '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
		unset( $_COOKIE[ self::COOKIE ] );
	}

	public function add_settings_page() { add_submenu_page( 'edit.php?post_type=fdm-menu', __( 'Table ordering settings', 'food-and-drink-menu' ), __( 'Table ordering', 'food-and-drink-menu' ), 'manage_options', 'fdm-table-settings', array( $this, 'render_settings' ) ); }
	public function save_tables() {
		global $fdm_controller;
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! current_user_can( 'manage_options' ) || ! $fdm_controller->permissions->check_permission( 'ordering' ) ) wp_die( esc_html__( 'Table configuration is unavailable.', 'food-and-drink-menu' ), '', array( 'response' => 403 ) );
		check_admin_referer( 'fdm_save_tables' );
		$rows = isset( $_POST['tables'] ) && is_array( $_POST['tables'] ) ? wp_unslash( $_POST['tables'] ) : array();
		$enabled = ! empty( $_POST['enabled'] );
		$result = $this->save_configuration( $rows, $enabled );
		$notice = array( 'error' => is_wp_error( $result ), 'message' => is_wp_error( $result ) ? $result->get_error_message() : __( 'Table settings saved.', 'food-and-drink-menu' ) );
		if ( is_wp_error( $result ) ) {
			$notice['enabled'] = $enabled;
			$notice['rows'] = array();
			foreach ( array_slice( $rows, 0, 50 ) as $row ) {
				if ( ! is_array( $row ) ) continue;
				$notice['rows'][] = array( 'id' => is_string( $row['id'] ?? null ) ? sanitize_key( $row['id'] ) : '', 'label' => is_string( $row['label'] ?? null ) ? sanitize_text_field( $row['label'] ) : '', 'page_id' => absint( $row['page_id'] ?? 0 ), 'active' => ! empty( $row['active'] ), 'rotate' => ! empty( $row['rotate'] ), 'delete' => ! empty( $row['delete'] ) );
			}
		}
		set_transient( 'fdm_table_save_notice_' . get_current_user_id(), $notice, 5 * MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'edit.php?post_type=fdm-menu&page=fdm-table-settings' ) ); exit;
	}

	/** Validate the complete configuration before any write; retain all prior settings on error. */
	public function save_configuration( $rows, $enabled ) {
		$settings = $this->settings();
		$existing = array_column( (array) ( $settings['fdm-dine-in-tables'] ?? array() ), null, 'id' );
		if ( ! is_array( $rows ) || count( $rows ) > 50 ) return new WP_Error( 'fdm_table_limit', __( 'Configure at most 50 tables.', 'food-and-drink-menu' ) );
		$tables = array(); $labels = array(); $ids = array();
		foreach ( $rows as $row ) {
			if ( is_array( $row ) && ! empty( $row['delete'] ) ) continue;
			if ( ! is_array( $row ) || ! is_string( $row['label'] ?? null ) ) continue;
			$label = sanitize_text_field( $row['label'] );
			if ( '' === $label ) continue;
			$id = is_string( $row['id'] ?? null ) ? sanitize_key( $row['id'] ) : '';
			if ( '' === $id ) $id = wp_generate_uuid4();
			elseif ( ! isset( $existing[ $id ] ) ) return new WP_Error( 'fdm_table_id', __( 'Invalid table identifier.', 'food-and-drink-menu' ) );
			$page = get_post( absint( $row['page_id'] ?? 0 ) );
			if ( strlen( $label ) > 64 || isset( $labels[ strtolower( $label ) ] ) || isset( $ids[ $id ] )
				|| ! $page || 'page' !== $page->post_type || 'publish' !== $page->post_status || ! $this->page_menu_ids( $page ) ) return new WP_Error( 'fdm_table_page', __( 'Use unique table labels and published pages containing a Menu block or shortcode.', 'food-and-drink-menu' ) );
			$labels[ strtolower( $label ) ] = true; $ids[ $id ] = true;
			$rotation = ! empty( $row['rotate'] ) || empty( $existing[ $id ]['rotation'] ) ? wp_generate_uuid4() : $existing[ $id ]['rotation'];
			$tables[] = array( 'id' => $id, 'label' => $label, 'page_id' => $page->ID, 'active' => ! empty( $row['active'] ), 'rotation' => $rotation );
		}
		$settings['fdm-dine-in-enabled'] = (bool) $enabled;
		$settings['fdm-dine-in-tables'] = $tables;
		// SAP's tab sanitizer only accepts fields from its own settings form. These
		// values were validated above, so fence this one write from that unrelated tab.
		$validated = function() use ( $settings ) { return $settings; };
		add_filter( 'sanitize_option_food-and-drink-menu-settings', $validated, PHP_INT_MAX );
		try { $updated = update_option( 'food-and-drink-menu-settings', $settings ); }
		finally { remove_filter( 'sanitize_option_food-and-drink-menu-settings', $validated, PHP_INT_MAX ); }
		return $updated || get_option( 'food-and-drink-menu-settings' ) === $settings ? true : new WP_Error( 'fdm_table_save', __( 'Table settings could not be saved. Please try again.', 'food-and-drink-menu' ) );
	}

	public function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$settings = $this->settings();
		$tables = (array) ( $settings['fdm-dine-in-tables'] ?? array() );
		$notice_key = 'fdm_table_save_notice_' . get_current_user_id();
		$notice = get_transient( $notice_key );
		if ( is_array( $notice ) ) {
			delete_transient( $notice_key );
			if ( ! empty( $notice['error'] ) ) { $tables = $notice['rows'] ?? array(); $settings['fdm-dine-in-enabled'] = ! empty( $notice['enabled'] ); }
		}
		if ( count( $tables ) < 50 ) $tables[] = array( 'id' => '', 'label' => '', 'page_id' => 0, 'active' => false );
		echo '<div class="wrap"><h1>' . esc_html__( 'Table ordering', 'food-and-drink-menu' ) . '</h1>';
		echo '<p class="fdm-table-overview">' . esc_html__( 'Link your restaurant tables to menu pages so diners can open a table-specific link or QR code and place orders for their table. Enable dine-in ordering turns this ordering mode on for active tables; turning it off keeps your table setup but stops accepting dine-in orders through these links.', 'food-and-drink-menu' ) . '</p>';
		echo '<p class="fdm-table-column-help">' . esc_html__( 'Table label identifies the table on dine-in orders. Menu page selects the published page containing its menu. Active enables the individual table link; clear it to temporarily disable a table. Rotate token replaces the table link when saved, invalidating old links and QR codes that must then be replaced. QR preview lets you copy the table link or request a QR image. Delete on save removes the row and invalidates its links and QR codes when you click Save Changes. Changes take effect only after saving.', 'food-and-drink-menu' ) . '</p>';
		echo '<p>' . esc_html__( 'Requires paid ordering and HTTPS. QR codes are public and can be copied; they do not prove that a diner is at the table. Preview links send the public menu URL and table token to api.qrserver.com only when clicked.', 'food-and-drink-menu' ) . '</p>';
		if ( is_array( $notice ) ) echo '<div class="notice ' . ( ! empty( $notice['error'] ) ? 'notice-error' : 'notice-success' ) . '"><p role="status">' . esc_html( $notice['message'] ) . '</p></div>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="fdm_save_tables">';
		wp_nonce_field( 'fdm_save_tables' );
		echo '<label><input type="checkbox" name="enabled" value="1" ' . checked( ! empty( $settings['fdm-dine-in-enabled'] ), true, false ) . '>' . esc_html__( 'Enable dine-in ordering', 'food-and-drink-menu' ) . '</label><table class="widefat"><thead><tr>';
		foreach ( array( __( 'Table label', 'food-and-drink-menu' ), __( 'Menu page', 'food-and-drink-menu' ), __( 'Active', 'food-and-drink-menu' ), __( 'Rotate token', 'food-and-drink-menu' ), __( 'QR preview', 'food-and-drink-menu' ), __( 'Delete on save', 'food-and-drink-menu' ) ) as $label ) echo '<th>' . esc_html( $label ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $tables as $index => $table ) {
			$prefix = 'tables[' . $index . ']';
			echo '<tr><td><input type="hidden" name="' . esc_attr( $prefix . '[id]' ) . '" value="' . esc_attr( $table['id'] ) . '"><input aria-label="' . esc_attr__( 'Table label', 'food-and-drink-menu' ) . '" name="' . esc_attr( $prefix . '[label]' ) . '" value="' . esc_attr( $table['label'] ) . '" maxlength="64"></td><td>';
			$page_control_id = 'fdm-table-page-' . $index;
			echo '<label class="screen-reader-text" for="' . esc_attr( $page_control_id ) . '">' . esc_html__( 'Menu page', 'food-and-drink-menu' ) . '</label>';
			$pages_html = wp_dropdown_pages( array( 'echo' => false, 'id' => esc_attr( $page_control_id ), 'name' => esc_attr( $prefix . '[page_id]' ), 'selected' => absint( $table['page_id'] ), 'show_option_none' => esc_html__( 'Select a menu page', 'food-and-drink-menu' ) ) );
			echo wp_kses( $pages_html, array( 'select' => array( 'id' => true, 'name' => true, 'class' => true ), 'option' => array( 'value' => true, 'selected' => true, 'disabled' => true, 'class' => true ), 'optgroup' => array( 'label' => true, 'disabled' => true ) ) );
			echo '</td><td><input aria-label="' . esc_attr__( 'Table active', 'food-and-drink-menu' ) . '" type="checkbox" name="' . esc_attr( $prefix . '[active]' ) . '" value="1" ' . checked( $table['active'], true, false ) . '></td><td><input aria-label="' . esc_attr__( 'Rotate table token', 'food-and-drink-menu' ) . '" type="checkbox" name="' . esc_attr( $prefix . '[rotate]' ) . '" value="1" ' . checked( ! empty( $table['rotate'] ), true, false ) . '></td><td>';
			if ( $table['id'] && empty( $notice['error'] ) ) {
				echo '<input type="url" readonly aria-label="' . esc_attr__( 'Table ordering URL', 'food-and-drink-menu' ) . '" value="' . esc_attr( $this->table_url( $table ) ) . '"> ';
				echo '<a target="_blank" rel="noopener noreferrer" href="' . esc_url( add_query_arg( array( 'size' => '300x300', 'data' => $this->table_url( $table ) ), 'https://api.qrserver.com/v1/create-qr-code/' ) ) . '">' . esc_html__( 'Request QR preview', 'food-and-drink-menu' ) . '</a>';
			}
			/* translators: %s: Table label. */
			$delete_label = sprintf( __( 'Delete table %s on save', 'food-and-drink-menu' ), $table['label'] );
			echo '</td><td><input type="checkbox" aria-label="' . esc_attr( $delete_label ) . '" name="' . esc_attr( $prefix . '[delete]' ) . '" value="1" ' . checked( ! empty( $table['delete'] ), true, false ) . '></td></tr>';
		}
		echo '</tbody></table>'; submit_button(); echo '</form></div>';
	}
}
