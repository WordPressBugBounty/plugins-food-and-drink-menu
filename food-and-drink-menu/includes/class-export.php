<?php

/**
 * Class to handle everything related to the walk-through that runs on plugin activation
 */

if ( !defined( 'ABSPATH' ) )
	exit;

if (!class_exists('ComposerAutoloaderInit4618f5c41cf5e27cc7908556f031e4d4')) {require_once FDM_PLUGIN_DIR . '/lib/PHPSpreadsheet/vendor/autoload.php';}
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
class fdmExport {

	public function __construct() {
		add_action( 'admin_menu', array($this, 'register_install_screen' ));

		if ( isset( $_POST['fdm_export_menu_items'] ) ) { add_action( 'admin_menu', array($this, 'export_menu_items' )); }
		if ( isset( $_POST['fdm_export_orders'] ) ) { add_action( 'admin_menu', array($this, 'export_orders' )); }
	}

	public function register_install_screen() {
		add_submenu_page( 
			'edit.php?post_type=fdm-menu', 
			'Export Menu', 
			'Export', 
			'manage_options', 
			'fdm-export', 
			array($this, 'display_export_screen') 
		);
	}

	public function display_export_screen() {
		global $fdm_controller;

		$export_permission = $fdm_controller->permissions->check_permission("export");

		?>
		<div class='wrap'>
			<h2>Export</h2>
			<?php if ( $export_permission ) { ?> 
				<form method='post'>
					<input type='submit' name='fdm_export_menu_items' value='Export Menu Items' class='button button-primary' />
					<input type='submit' name='fdm_export_orders' value='Export Orders' class='button button-primary' />
				</form>
			<?php } else { ?>
				<div class='fdm-premium-locked'>
					<a href="https://www.fivestarplugins.com/license-payment/?Selected=FDM&Quantity=1&utm_source=fdm_admin_export_page" target="_blank">Upgrade</a> to the premium version to use this feature
				</div>
			<?php } ?>
		</div>
	<?php }

	public function export_menu_items() {
		global $fdm_controller;

		$fields = $fdm_controller->settings->get_menu_item_custom_fields();

		// Instantiate a new PHPExcel object
		$spreadsheet = new Spreadsheet();
		// Set the active Excel worksheet to sheet 0
		$spreadsheet->setActiveSheetIndex(0);

		// Print out the regular order field labels
		$spreadsheet->getActiveSheet()->setCellValue("A1", "ID");
		$spreadsheet->getActiveSheet()->setCellValue("B1", "Title");
		$spreadsheet->getActiveSheet()->setCellValue("C1", "Description");
		$spreadsheet->getActiveSheet()->setCellValue("D1", "Price");
		$spreadsheet->getActiveSheet()->setCellValue("E1", "Sections");

		$column = 'F';
		foreach ($fields as $field) {
			if ( $field->type != 'section' ) :
     			$spreadsheet->getActiveSheet()->setCellValue($column."1", $field->name);
    			$column++;
    		endif;
		}  

		//start while loop to get data
		$row_count = 2;

		$params = array(
			'posts_per_page' => -1,
			'post_type' => 'fdm-menu-item'
		);

		$menu_items = get_posts($params);

		foreach ( $menu_items as $menu_item ) {

    	 	$values = get_post_meta( $menu_item->ID, '_fdm_menu_item_custom_fields', true );
			if ( ! is_array($values ) ) { $values = array(); }

    	 	$prices = (array) get_post_meta( $menu_item->ID, 'fdm_item_price' );

			if ( empty( $prices ) ) {
				$prices = array( '' );
			}

			$prices_string = implode( ';', $prices );

    	 	$sections = get_the_terms($menu_item->ID, "fdm-menu-section");

			$sections_string = '';

			if ( is_array( $sections ) ) {

    	 		foreach ( $sections  as $section ) {

    	 			$sections_string .= $section->name . ",";
    	 		}

    	 		$sections_string = trim($sections_string, ",");
    	 	}
    	 	else { $sections_string = ""; }

    	 	$spreadsheet->getActiveSheet()->setCellValue("A" . $row_count, $menu_item->ID);
			$spreadsheet->getActiveSheet()->setCellValue("B" . $row_count, $menu_item->post_title);
			$spreadsheet->getActiveSheet()->setCellValue("C" . $row_count, $menu_item->post_content);
			$spreadsheet->getActiveSheet()->setCellValue("D" . $row_count, $prices_string);
			$spreadsheet->getActiveSheet()->setCellValue("E" . $row_count, $sections_string);

			$column = 'F';

			foreach ($fields as $field) {

				if ( $field->type != 'section' ) {

     				if ( isset( $values[ $field->slug ] ) ) {

     					$spreadsheet->getActiveSheet()->setCellValue( $column . $row_count, ( is_array( $values[$field->slug] ) ? implode( ',', $values[ $field->slug ] ) : $values[ $field->slug ] ) );
     				}
     				
     				$column++;
    			}
			}  

    		$row_count++;

    		unset($prices_string);
    		unset($sections_string);
		}

		// Redirect output to a client’s web browser (Excel5)
		if (!isset($format_type) == "csv") {
			header('Content-Type: application/vnd.ms-excel');
			header('Content-Disposition: attachment;filename="menu_items_export.csv"');
			header('Cache-Control: max-age=0');
			$objWriter = new Csv($spreadsheet);
			$objWriter->save('php://output');
			die();
		}
		else {
			header('Content-Type: application/vnd.ms-excel');
			header('Content-Disposition: attachment;filename="menu_items_export.xls"');
			header('Cache-Control: max-age=0');
			$objWriter = new Xls($spreadsheet);
			$objWriter->save('php://output');
			die();
		}
	}

	public function export_orders() {
		global $fdm_controller;

		$fields = $fdm_controller->settings->get_ordering_custom_fields();

		// Instantiate a new PHPExcel object
		$spreadsheet = new Spreadsheet();
		// Set the active Excel worksheet to sheet 0
		$spreadsheet->setActiveSheetIndex(0);

		// Print out the regular order field labels
		$spreadsheet->getActiveSheet()->setCellValue( "A1", "ID" );
		$spreadsheet->getActiveSheet()->setCellValue( "B1", "Name" );
		$spreadsheet->getActiveSheet()->setCellValue( "C1", "Email" );
		$spreadsheet->getActiveSheet()->setCellValue( "D1", "Phone" );
		$spreadsheet->getActiveSheet()->setCellValue( "E1", "Note" );
		$spreadsheet->getActiveSheet()->setCellValue( "F1", "Order Items" );
		$spreadsheet->getActiveSheet()->setCellValue( "G1", "Pickup Time" );
		$spreadsheet->getActiveSheet()->setCellValue( "H1", "Delivery" );
		$spreadsheet->getActiveSheet()->setCellValue( "I1", "Payment Amount" );
		$spreadsheet->getActiveSheet()->setCellValue( "J1", "Tip Amount" );
		$spreadsheet->getActiveSheet()->setCellValue( "K1", "Receipt ID" );

		$column = 'L';
		foreach ($fields as $field) {
			
			$spreadsheet->getActiveSheet()->setCellValue($column."1", $field->name);
    		$column++;
		}  

		//start while loop to get data
		$row_count = 2;

		$params = array(
			'posts_per_page' 	=> -1,
			'post_type' 		=> FDM_ORDER_POST_TYPE,
			'date_query'		=> array(
				'after'				=> apply_filters( 'fdm_order_export_time', '24 hours ago' )
			)
		);

		$order_posts = get_posts( $params );

		foreach ( $order_posts as $order_post ) {

			$order = new fdmOrderItem();

			$order->load( $order_post );

			$order_items = '';

			foreach ( $this->order->get_order_items() as $order_item ) { 

				$order_items .= '•' . get_the_title( $order_item->id ) . "\n";
				$order_items .= '    ' . $order_item->quantity . "\n";
				$order_items .= '    ' . fdm_calculate_admin_price( $order_item ) . "\n";
				$order_items .= ! empty( $order_item->note ) ? '    Note: ' . $order_item->note . "\n" : '';

				if ( ! empty( $order_item->selected_options ) ) {

					$order_items .= "    Options\n";

					$ordering_options = get_post_meta( $order_item->id, '_fdm_ordering_options', true );
					$ordering_options = is_array( $ordering_options ) ? $ordering_options : array();
			
					foreach( $order_item->selected_options as $selected_option ) {
			
						if ( ! array_key_exists( $selected_option, $ordering_options ) ) { continue; }
			
						echo '    •' . $ordering_options[ $selected_option ]['name'] . ' (' . fdm_format_price( $ordering_options[ $selected_option ]['cost'] ) . ')' . "\n";
					}
				}

				$order_items .= "\n";
			}

    	 	$spreadsheet->getActiveSheet()->setCellValue( "A" . $row_count, $order->ID );
			$spreadsheet->getActiveSheet()->setCellValue( "B" . $row_count, $order->name );
			$spreadsheet->getActiveSheet()->setCellValue( "C" . $row_count, $order->email );
			$spreadsheet->getActiveSheet()->setCellValue( "D" . $row_count, $order->phone );
			$spreadsheet->getActiveSheet()->setCellValue( "E" . $row_count, $order->note );
			$spreadsheet->getActiveSheet()->setCellValue( "F" . $row_count, $order_items );
			$spreadsheet->getActiveSheet()->setCellValue( "G" . $row_count, $order->pickup_time );
			$spreadsheet->getActiveSheet()->setCellValue( "H" . $row_count, ( $order->delivery ? __( 'Yes', 'food-and-drink-menu' ) : __( 'No', 'food-and-drink-menu' ) ) );
			$spreadsheet->getActiveSheet()->setCellValue( "I" . $row_count, $order->payment_amount );
			$spreadsheet->getActiveSheet()->setCellValue( "J" . $row_count, $order->tip_amount );
			$spreadsheet->getActiveSheet()->setCellValue( "K" . $row_count, $order->receipt_id );

			$column = 'F';

			foreach ($fields as $field) {

				if ( isset( $values[ $field->slug ] ) ) {

     				$spreadsheet->getActiveSheet()->setCellValue( $column . $row_count, ( is_array( $values[$field->slug] ) ? implode( ',', $values[ $field->slug ] ) : $values[ $field->slug ] ) );
     			}
     				
     			$column++;
			} 

			// Make the row heights dynamic to accomodate order items
			$spreadsheet->getActiveSheet()->getRowDimension( $row_count )->setRowHeight( -1 );
			$spreadsheet->getActiveSheet()->getStyle( 'F' . $row_count )->getAlignment()->setWrapText(true);

    		$row_count++;

    		unset($order_items);
		}

		// Redirect output to a client’s web browser (Excel5)
		if (!isset($format_type) == "csv") {
			header('Content-Type: application/vnd.ms-excel');
			header('Content-Disposition: attachment;filename="orders_export.csv"');
			header('Cache-Control: max-age=0');
			$objWriter = new Csv($spreadsheet);
			$objWriter->save('php://output');
			die();
		}
		else {
			header('Content-Type: application/vnd.ms-excel');
			header('Content-Disposition: attachment;filename="orders_export.xls"');
			header('Cache-Control: max-age=0');
			$objWriter = new Xls($spreadsheet);
			$objWriter->save('php://output');
			die();
		}
	}

}


