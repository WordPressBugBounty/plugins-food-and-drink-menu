<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class fdmMenuAuthoring {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_reorder_page' ) );
		add_action( 'admin_post_fdm_save_menu_item_order', array( $this, 'handle_save_order' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_setup_meta_boxes' ) );
		add_filter( 'manage_fdm-menu-item_posts_columns', array( $this, 'add_order_column' ), 20 );
		add_filter( 'manage_edit-fdm-menu-item_sortable_columns', array( $this, 'add_sortable_order_column' ), 20 );
		add_action( 'manage_fdm-menu-item_posts_custom_column', array( $this, 'render_order_column' ), 20, 2 );
		add_action( 'pre_get_posts', array( $this, 'apply_order_column_sort' ), 20 );
	}

	public function add_reorder_page() {
		add_submenu_page(
			'edit.php?post_type=' . FDM_MENU_POST_TYPE,
			__( 'Reorder Menu Items', 'food-and-drink-menu' ),
			__( 'Reorder Items', 'food-and-drink-menu' ),
			'edit_posts',
			'fdm-reorder-items',
			array( $this, 'render_reorder_page' )
		);
	}

	public function enqueue_assets( $hook ) {
		if ( 'fdm-menu_page_fdm-reorder-items' !== $hook ) return;
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script( 'fdm-menu-item-order', FDM_PLUGIN_URL . '/assets/js/menu-item-order.js', array( 'jquery', 'jquery-ui-sortable' ), FDM_VERSION, true );
		wp_enqueue_style( 'fdm-menu-item-order', FDM_PLUGIN_URL . '/assets/css/menu-item-order.css', array(), FDM_VERSION );
	}

	public function render_reorder_page() {
		if ( ! current_user_can( 'edit_posts' ) ) wp_die( esc_html__( 'You are not allowed to reorder menu items.', 'food-and-drink-menu' ) );
		$items = get_posts( array( 'post_type' => FDM_MENUITEM_POST_TYPE, 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'posts_per_page' => -1, 'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC' ) ) );
		?>
		<div class="wrap fdm-reorder-wrap">
			<h1><?php esc_html_e( 'Reorder Menu Items', 'food-and-drink-menu' ); ?></h1>
			<p><?php esc_html_e( 'Item order is global and applies anywhere the item appears.', 'food-and-drink-menu' ); ?></p>
			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success flag emitted by the nonce-protected save redirect. ?>
			<?php if ( isset( $_GET['fdm-order-updated'] ) ) : ?><div class="notice notice-success"><p role="status"><?php esc_html_e( 'Menu item order saved.', 'food-and-drink-menu' ); ?></p></div><?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="fdm_save_menu_item_order">
				<?php wp_nonce_field( 'fdm_save_menu_item_order' ); ?>
				<input type="hidden" id="fdm-menu-item-order" name="ordered_ids" value="<?php echo esc_attr( implode( ',', wp_list_pluck( $items, 'ID' ) ) ); ?>">
				<ul id="fdm-reorder-items" aria-label="<?php esc_attr_e( 'Menu items in global display order', 'food-and-drink-menu' ); ?>">
					<?php foreach ( $items as $item ) : $terms = wp_get_post_terms( $item->ID, 'fdm-menu-section', array( 'fields' => 'names' ) ); ?>
						<li data-id="<?php echo absint( $item->ID ); ?>" tabindex="0">
							<span class="dashicons dashicons-move" aria-hidden="true"></span>
							<strong><?php echo esc_html( $item->post_title ); ?></strong>
							<span class="fdm-reorder-sections"><?php echo esc_html( $terms ? implode( ', ', $terms ) : __( 'Unassigned', 'food-and-drink-menu' ) ); ?></span>
							<button type="button" class="button-link fdm-move-up"><?php esc_html_e( 'Move up', 'food-and-drink-menu' ); ?></button>
							<button type="button" class="button-link fdm-move-down"><?php esc_html_e( 'Move down', 'food-and-drink-menu' ); ?></button>
						</li>
					<?php endforeach; ?>
				</ul>
				<?php submit_button( __( 'Save Order', 'food-and-drink-menu' ) ); ?>
			</form>
		</div>
		<?php
	}

	public function handle_save_order() {
		if ( 'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) || ! current_user_can( 'edit_posts' ) ) wp_die( esc_html__( 'You are not allowed to reorder menu items.', 'food-and-drink-menu' ) );
		check_admin_referer( 'fdm_save_menu_item_order' );
		$ordered_ids = isset( $_POST['ordered_ids'] ) ? explode( ',', sanitize_text_field( wp_unslash( $_POST['ordered_ids'] ) ) ) : array();
		$result = $this->save_order( $ordered_ids );
		if ( is_wp_error( $result ) ) wp_die( esc_html( $result->get_error_message() ) );
		wp_safe_redirect( add_query_arg( 'fdm-order-updated', '1', admin_url( 'edit.php?post_type=' . FDM_MENU_POST_TYPE . '&page=fdm-reorder-items' ) ) );
		exit;
	}

	public function save_order( $ordered_ids ) {
		$ordered_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $ordered_ids ) ) ) );
		$current_ids = get_posts( array( 'post_type' => FDM_MENUITEM_POST_TYPE, 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'posts_per_page' => -1, 'fields' => 'ids' ) );
		sort( $current_ids );
		$submitted = $ordered_ids;
		sort( $submitted );
		if ( $current_ids !== $submitted ) return new WP_Error( 'fdm_incomplete_order', __( 'The submitted order must contain every current menu item exactly once.', 'food-and-drink-menu' ) );
		foreach ( $ordered_ids as $index => $post_id ) {
			$result = wp_update_post( array( 'ID' => $post_id, 'menu_order' => ( $index + 1 ) * 10 ), true );
			if ( is_wp_error( $result ) ) return $result;
		}
		return true;
	}

	public function add_setup_meta_boxes() {
		add_meta_box( 'fdm-menu-setup-status', __( 'Menu Setup', 'food-and-drink-menu' ), array( $this, 'render_menu_setup' ), FDM_MENU_POST_TYPE, 'side', 'high' );
		add_meta_box( 'fdm-menu-item-section-status', __( 'Menu Placement', 'food-and-drink-menu' ), array( $this, 'render_item_section_status' ), FDM_MENUITEM_POST_TYPE, 'side', 'high' );
	}

	public function render_menu_setup( $post ) {
		$item_count = (int) wp_count_posts( FDM_MENUITEM_POST_TYPE )->publish + (int) wp_count_posts( FDM_MENUITEM_POST_TYPE )->draft;
		$sections = get_terms( array( 'taxonomy' => 'fdm-menu-section', 'hide_empty' => false, 'fields' => 'ids' ) );
		$sections = is_wp_error( $sections ) ? array() : $sections;
		$unassigned = get_posts( array( 'post_type' => FDM_MENUITEM_POST_TYPE, 'post_status' => array( 'publish', 'draft' ), 'posts_per_page' => 1, 'fields' => 'ids', 'tax_query' => array( array( 'taxonomy' => 'fdm-menu-section', 'operator' => 'NOT EXISTS' ) ) ) );
		$included = array_filter( array_merge( explode( ',', (string) get_post_meta( $post->ID, 'fdm_menu_column_one', true ) ), explode( ',', (string) get_post_meta( $post->ID, 'fdm_menu_column_two', true ) ) ) );
		$rows = array(
			array( $item_count > 0, __( 'Menu items exist', 'food-and-drink-menu' ), admin_url( 'post-new.php?post_type=' . FDM_MENUITEM_POST_TYPE ), __( 'Add your first menu item', 'food-and-drink-menu' ) ),
			array( ! empty( $sections ), __( 'Sections exist', 'food-and-drink-menu' ), admin_url( 'edit-tags.php?taxonomy=fdm-menu-section&post_type=' . FDM_MENUITEM_POST_TYPE ), __( 'Create a menu section', 'food-and-drink-menu' ) ),
			array( empty( $unassigned ), __( 'Items are assigned to sections', 'food-and-drink-menu' ), admin_url( 'edit.php?post_type=' . FDM_MENUITEM_POST_TYPE . '&section=-1' ), __( 'Review unassigned items', 'food-and-drink-menu' ) ),
			array( ! empty( $included ), __( 'Sections are included in this menu', 'food-and-drink-menu' ), '#fdm_menu_column_one_list', __( 'Drag sections into the menu organizer', 'food-and-drink-menu' ) ),
			array( 'publish' === $post->post_status, __( 'Menu is published', 'food-and-drink-menu' ), '#publish', __( 'Publish this menu', 'food-and-drink-menu' ) ),
		);
		echo '<ul class="fdm-menu-setup-list">';
		foreach ( $rows as $row ) echo '<li><span aria-hidden="true">' . ( $row[0] ? '&#10003;' : '&#9888;' ) . '</span> ' . esc_html( $row[1] ) . ( $row[0] ? '' : ' — <a href="' . esc_url( $row[2] ) . '">' . esc_html( $row[3] ) . '</a>' ) . '</li>';
		echo '</ul>';
		if ( 'publish' === $post->post_status ) {
			echo '<p><label for="fdm-menu-shortcode">' . esc_html__( 'Shortcode', 'food-and-drink-menu' ) . '</label><input id="fdm-menu-shortcode" class="widefat" readonly value="' . esc_attr( '[fdm-menu id="' . $post->ID . '"]' ) . '"></p>';
			echo '<p>' . esc_html__( 'You can also add the Five Star Menu block in the block editor.', 'food-and-drink-menu' ) . '</p>';
			if ( get_permalink( $post ) ) echo '<p><a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html__( 'View published menu', 'food-and-drink-menu' ) . '</a></p>';
		}
	}

	public function render_item_section_status( $post ) {
		$terms = wp_get_post_terms( $post->ID, 'fdm-menu-section', array( 'fields' => 'ids' ) );
		if ( $terms ) echo '<p><span aria-hidden="true">&#10003;</span> ' . esc_html__( 'This item is assigned to a menu section.', 'food-and-drink-menu' ) . '</p>';
		else echo '<p class="notice-warning"><span aria-hidden="true">&#9888;</span> ' . esc_html__( 'This item is not assigned to a section. You can save it as a draft, but it will not appear in a menu until assigned.', 'food-and-drink-menu' ) . '</p><p><a href="' . esc_url( admin_url( 'edit-tags.php?taxonomy=fdm-menu-section&post_type=' . FDM_MENUITEM_POST_TYPE ) ) . '">' . esc_html__( 'Create or manage sections', 'food-and-drink-menu' ) . '</a></p>';
	}

	public function add_order_column( $columns ) {
		$updated = array();
		foreach ( $columns as $key => $label ) {
			$updated[ $key ] = $label;
			if ( 'title' === $key ) $updated['menu_order'] = __( 'Order', 'food-and-drink-menu' );
		}
		return $updated;
	}

	public function add_sortable_order_column( $columns ) { $columns['menu_order'] = 'menu_order'; return $columns; }
	public function render_order_column( $column, $post_id ) { if ( 'menu_order' === $column ) echo absint( get_post_field( 'menu_order', $post_id ) ); }
	public function apply_order_column_sort( $query ) {
		if ( is_admin() && $query->is_main_query() && FDM_MENUITEM_POST_TYPE === $query->get( 'post_type' ) && 'menu_order' === $query->get( 'orderby' ) ) $query->set( 'orderby', array( 'menu_order' => 'ASC', 'title' => 'ASC' ) );
	}
}
