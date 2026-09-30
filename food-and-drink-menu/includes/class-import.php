<?php

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'fdmImport' ) ) {
/**
 * Secure menu-item spreadsheet import.
 */
class fdmImport {

	const ACTION = 'fdm_import_menu_items';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_install_screen' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'import_menu_items' ) );
	}

	public function register_install_screen() {
		add_submenu_page(
			'edit.php?post_type=fdm-menu',
			__( 'Import Menu', 'food-and-drink-menu' ),
			__( 'Import', 'food-and-drink-menu' ),
			'manage_options',
			'fdm-import',
			array( $this, 'display_import_screen' )
		);
	}

	public function display_import_screen() {
		global $fdm_controller;
		$allowed = $fdm_controller->permissions->check_permission( 'import' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status set by a safe post-action redirect and restricted to a fixed allowlist below.
		$notice = isset( $_GET['fdm_import_notice'] ) ? sanitize_key( wp_unslash( $_GET['fdm_import_notice'] ) ) : '';
		$notices = array(
			'success'       => __( 'Menu items imported successfully.', 'food-and-drink-menu' ),
			'invalid_file'  => __( 'Choose a valid CSV, XLS, or XLSX spreadsheet.', 'food-and-drink-menu' ),
			'invalid_sheet' => __( 'The spreadsheet could not be read or is missing required Title and Price columns.', 'food-and-drink-menu' ),
			'invalid_row'   => __( 'The import stopped because a row contains invalid data or targets a non-menu item.', 'food-and-drink-menu' ),
			'failed'        => __( 'The import could not be completed. No server file path has been exposed.', 'food-and-drink-menu' ),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Import', 'food-and-drink-menu' ); ?></h1>
			<?php if ( isset( $notices[ $notice ] ) ) : ?>
				<div class="notice <?php echo 'success' === $notice ? 'notice-success' : 'notice-error'; ?> is-dismissible"><p><?php echo esc_html( $notices[ $notice ] ); ?></p></div>
			<?php endif; ?>
			<?php if ( $allowed ) : ?>
				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" enctype="multipart/form-data">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
					<?php wp_nonce_field( self::ACTION ); ?>
					<p>
						<label for="fdm_menu_items_spreadsheet"><?php esc_html_e( 'Spreadsheet Containing Menu Items', 'food-and-drink-menu' ); ?></label><br />
						<input id="fdm_menu_items_spreadsheet" name="fdm_menu_items_spreadsheet" type="file" accept=".csv,.xls,.xlsx" required />
					</p>
					<?php submit_button( __( 'Import Menu Items', 'food-and-drink-menu' ) ); ?>
				</form>
			<?php else : ?>
				<div class="fdm-premium-locked"><a href="https://www.fivestarplugins.com/license-payment/?Selected=FDM&amp;Quantity=1&amp;utm_source=fdm_admin_import_page" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Upgrade', 'food-and-drink-menu' ); ?></a> <?php esc_html_e( 'to the premium version to use this feature.', 'food-and-drink-menu' ); ?></div>
			<?php endif; ?>
		</div>
		<?php
	}

	public function import_menu_items() {
		global $fdm_controller;

		if ( 'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) ) {
			wp_die( esc_html__( 'Invalid request method.', 'food-and-drink-menu' ), '', array( 'response' => 405 ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to import menu items.', 'food-and-drink-menu' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::ACTION );
		if ( ! $fdm_controller->permissions->check_permission( 'import' ) ) {
			wp_die( esc_html__( 'A paid entitlement is required to import menu items.', 'food-and-drink-menu' ), '', array( 'response' => 403 ) );
		}

		$uploaded_file = '';
		$notice = 'failed';
		try {
			$uploaded_file = $this->handle_spreadsheet_upload();
			$this->process_spreadsheet( $uploaded_file );
			do_action( 'fdm_import_completed' );
			$notice = 'success';
		} catch ( UnexpectedValueException $exception ) {
			$notice = 'invalid_file';
		} catch ( DomainException $exception ) {
			$notice = 'invalid_row';
		} catch ( Throwable $exception ) {
			$notice = 'failed';
		} finally {
			if ( $uploaded_file && file_exists( $uploaded_file ) ) {
				wp_delete_file( $uploaded_file );
			}
		}
		$this->redirect_with_notice( $notice );
	}

	private function handle_spreadsheet_upload() {
		// The caller has already passed capability and admin nonce checks.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( empty( $_FILES['fdm_menu_items_spreadsheet'] ) || ! is_array( $_FILES['fdm_menu_items_spreadsheet'] ) ) {
			throw new UnexpectedValueException( 'missing_upload' );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- wp_handle_upload() requires the original PHP upload array; name is sanitized before use.
		$file = $_FILES['fdm_menu_items_spreadsheet'];
		if ( ! isset( $file['error'] ) || UPLOAD_ERR_OK !== (int) $file['error'] || empty( $file['tmp_name'] ) || empty( $file['name'] ) ) {
			throw new UnexpectedValueException( 'upload_error' );
		}

		$name = sanitize_file_name( wp_unslash( $file['name'] ) );
		$file['name'] = $name;
		$extension = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( ! in_array( $extension, array( 'csv', 'xls', 'xlsx' ), true ) ) {
			throw new UnexpectedValueException( 'invalid_extension' );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		$upload = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
				'mimes'     => array(
					'csv'  => 'text/csv|text/plain|application/csv',
					'xls'  => 'application/vnd.ms-excel',
					'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
				),
			)
		);
		if ( ! empty( $upload['error'] ) || empty( $upload['file'] ) ) {
			throw new UnexpectedValueException( 'invalid_upload' );
		}

		return $upload['file'];
	}

	private function process_spreadsheet( $path ) {
		global $fdm_controller;
		$this->load_spreadsheet_library();

		$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile( $path );
		$extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		$reader_class = strtolower( get_class( $reader ) );
		if ( false === strpos( $reader_class, '\\' . $extension ) ) {
			throw new UnexpectedValueException( 'content_extension_mismatch' );
		}
		if ( method_exists( $reader, 'setReadDataOnly' ) ) {
			$reader->setReadDataOnly( true );
		}

		$spreadsheet = $reader->load( $path );
		try {
			$sheet = $spreadsheet->getActiveSheet();
			$headers = $this->read_headers( $sheet );
			$fields = $fdm_controller->settings->get_menu_item_custom_fields();
			$field_headers = array();
			$legacy_names = array_count_values( array_map( function( $field ) { return (string) $field->name; }, $fields ) );
			$reserved = array( 'ID', 'Title', 'Description', 'Price', 'Sections' );
			$fields_by_slug = array();
			foreach ( $fields as $field ) {
				if ( 'section' === $field->type ) continue;
				$fields_by_slug[ sanitize_key( $field->slug ) ] = $field;
				if ( 1 === $legacy_names[ (string) $field->name ] && ! in_array( $field->name, $reserved, true ) ) {
					$field_headers[ $field->name ] = $field;
				}
			}
			foreach ( array_keys( $headers ) as $header ) {
				if ( preg_match( '/\[fdm:([a-z0-9_-]+)(?::[0-9]+)?\]$/', $header, $match ) && isset( $fields_by_slug[ $match[1] ] ) ) {
					$field_headers[ $header ] = $fields_by_slug[ $match[1] ];
				}
			}

			for ( $row = 2; $row <= (int) $sheet->getHighestRow(); $row++ ) {
				$this->import_row( $sheet, $row, $headers, $field_headers );
			}
		} finally {
			if ( method_exists( $spreadsheet, 'disconnectWorksheets' ) ) {
				$spreadsheet->disconnectWorksheets();
			}
			unset( $spreadsheet );
		}
	}

	private function read_headers( $sheet ) {
		$highest = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString( $sheet->getHighestColumn() );
		$headers = array();
		for ( $column = 1; $column <= $highest; $column++ ) {
			$header = trim( sanitize_text_field( (string) $sheet->getCellByColumnAndRow( $column, 1 )->getValue() ) );
			if ( '' === $header || isset( $headers[ $header ] ) ) {
				throw new UnexpectedValueException( 'invalid_headers' );
			}
			$headers[ $header ] = $column;
		}
		if ( ! isset( $headers['Title'], $headers['Price'] ) ) {
			throw new UnexpectedValueException( 'missing_required_headers' );
		}
		return $headers;
	}

	private function import_row( $sheet, $row, $headers, $field_headers ) {
		$title = sanitize_text_field( $this->restore_export_value( (string) $sheet->getCellByColumnAndRow( $headers['Title'], $row )->getValue() ) );
		if ( '' === $title ) return;

		$post = array(
			'post_title'   => $title,
			'post_content' => isset( $headers['Description'] ) ? wp_kses_post( $this->restore_export_value( (string) $sheet->getCellByColumnAndRow( $headers['Description'], $row )->getValue() ) ) : '',
			'post_status'  => 'publish',
			'post_type'    => FDM_MENUITEM_POST_TYPE,
		);

		if ( isset( $headers['ID'] ) ) {
			$raw_id = trim( (string) $sheet->getCellByColumnAndRow( $headers['ID'], $row )->getValue() );
			if ( '' !== $raw_id ) {
				$post_id = absint( $raw_id );
				if ( ! $post_id || FDM_MENUITEM_POST_TYPE !== get_post_type( $post_id ) ) throw new DomainException( 'invalid_target' );
				$post['ID'] = $post_id;
			}
		}

		$raw_prices = trim( $this->restore_export_value( (string) $sheet->getCellByColumnAndRow( $headers['Price'], $row )->getValue() ) );
		$prices = array();
		foreach ( '' === $raw_prices ? array() : explode( ';', $raw_prices ) as $price ) {
			$price = trim( wp_strip_all_tags( $price ) );
			if ( '' === $price || strlen( $price ) > 100 || preg_match( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $price ) ) throw new DomainException( 'invalid_price' );
			$prices[] = $price;
		}

		// Resolve every supplied section before changing this row's post or metadata.
		$section_ids = isset( $headers['Sections'] )
			? $this->resolve_section_references( $this->restore_export_value( (string) $sheet->getCellByColumnAndRow( $headers['Sections'], $row )->getValue() ) )
			: null;

		$result = isset( $post['ID'] ) ? wp_update_post( $post, true ) : wp_insert_post( $post, true );
		if ( is_wp_error( $result ) || ! $result ) throw new DomainException( 'post_write_failed' );

		delete_post_meta( $result, 'fdm_item_price' );
		foreach ( array_slice( $prices, 0, 20 ) as $price ) add_post_meta( $result, 'fdm_item_price', $price );

		if ( null !== $section_ids ) wp_set_object_terms( $result, $section_ids, 'fdm-menu-section' );

		$custom_values = array();
		foreach ( $field_headers as $header => $field ) {
			if ( ! isset( $headers[ $header ] ) || 'section' === $field->type ) continue;
			$value = $this->restore_export_value( $sheet->getCellByColumnAndRow( $headers[ $header ], $row )->getValue() );
			if ( 'checkbox' === $field->type ) {
				$custom_values[ $field->slug ] = array_values( array_filter( array_map( 'sanitize_text_field', array_map( 'trim', explode( ',', (string) $value ) ) ) ) );
			} elseif ( 'textarea' === $field->type ) {
				$custom_values[ $field->slug ] = sanitize_textarea_field( (string) $value );
			} else {
				$custom_values[ $field->slug ] = sanitize_text_field( (string) $value );
			}
		}
		update_post_meta( $result, '_fdm_menu_item_custom_fields', $custom_values );
	}

	private function resolve_section_references( $value ) {
		$section_ids = array();
		foreach ( explode( ',', $value ) as $section ) {
			$section = trim( $section );
			if ( '' === $section ) continue;
			$section = sanitize_text_field( $section );
			if ( '' === $section ) throw new DomainException( 'invalid_section' );
			if ( ctype_digit( $section ) ) {
				$term = get_term( absint( $section ), 'fdm-menu-section' );
			} else {
				// Exact names take precedence over slugs, including formula-leading names.
				$term = get_term_by( 'name', $section, 'fdm-menu-section' );
				if ( ! $term ) $term = get_term_by( 'slug', $section, 'fdm-menu-section' );
			}
			if ( ! ( $term instanceof WP_Term ) ) throw new DomainException( 'invalid_section' );
			$section_ids[] = (int) $term->term_id;
		}
		return array_values( array_unique( $section_ids ) );
	}

	private function restore_export_value( $value ) {
		$value = (string) $value;
		return preg_match( "/^'[[:space:]]*[=+\-@]/u", $value ) ? substr( $value, 1 ) : $value;
	}

	private function load_spreadsheet_library() {
		if ( ! class_exists( '\\PhpOffice\\PhpSpreadsheet\\IOFactory' ) ) require_once FDM_PLUGIN_DIR . '/lib/PHPSpreadsheet/vendor/autoload.php';
	}

	private function redirect_with_notice( $notice ) {
		wp_safe_redirect( add_query_arg( 'fdm_import_notice', sanitize_key( $notice ), admin_url( 'edit.php?post_type=fdm-menu&page=fdm-import' ) ) );
		exit;
	}
}
}
