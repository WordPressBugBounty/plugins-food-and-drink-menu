<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'fdmHelper' ) ) {
/**
 * Class to provide helper functions
 *
 * @since 5.1.0
 */
class fdmHelper {

  // Hold the class instance.
  private static $instance = null;

  // Links for the help button
  private static $documentation_link = 'https://doc.fivestarplugins.com/plugins/food-and-drink-menu/';
  private static $tutorials_link = 'https://www.youtube.com/playlist?list=PLEndQUuhlvSqy2KjpKfGpd-vAUJLfdYMI';
  private static $faq_link = 'https://doc.fivestarplugins.com/plugins/food-and-drink-menu/user/faq';
  private static $wp_forum_support_link = 'https://wordpress.org/support/plugin/food-and-drink-menu/';
  private static $support_center_link = 'https://www.fivestarplugins.com/support-center/';

  // Values for when to trigger the help button to display
  private static $post_types = array( FDM_MENU_POST_TYPE, FDM_MENUITEM_POST_TYPE );
  private static $taxonomies = array( 'fdm-menu-section', 'fdm-menu-item-flag' );
  private static $additional_pages = array(
    'fdm-dashboard',
    'fdm-orders',
    'fdm-add-edit-order',
    'fdm-export',
    'fdm-import',
    'food-and-drink-menu-settings'
  );

  /**
   * The constructor is private to prevent initiation with outer code.
   */
  private function __construct() {}

  /**
   * The object is created from within the class itself only if the class has no instance.
   */
  public static function getInstance() {

    if ( self::$instance == null ) {
      self::$instance = new fdmHelper();
    }

    return self::$instance;
  }

  /**
   * Register FDM help with AIAA when available.
   *
   * @since 5.1.0
   */
  public static function aiaa_add_filter() {

    add_filter( 'ait_aiaa_third_party_information', array( __CLASS__, 'add_help_to_aiaa' ), 20, 2 );
  }

  /**
   * Add FDM help content to AIAA's third-party Help tab.
   *
   * @param array $items
   * @param array $context
   * @return array
   *
   * @since 5.1.0
   */
  public static function add_help_to_aiaa( $items, $context ) {

    $items   = is_array( $items ) ? $items : array();
    $context = is_array( $context ) ? $context : array();

    $screen_id = isset( $context['screen_id'] ) ? (string) $context['screen_id'] : '';
    $post_type = isset( $context['post_type'] ) ? (string) $context['post_type'] : '';
    $taxonomy  = isset( $context['taxonomy'] ) ? (string) $context['taxonomy'] : '';

    if ( ! self::aiaa_matches_context( $screen_id, $post_type, $taxonomy ) ) { return $items; }

    $page_details = self::get_page_details_for_context( $context );

    $tutorial_links = array();
    if ( ! empty( $page_details['tutorials'] ) && is_array( $page_details['tutorials'] ) ) {

      foreach ( $page_details['tutorials'] as $tutorial ) {

        if ( empty( $tutorial['url'] ) || empty( $tutorial['title'] ) ) { continue; }

        $tutorial_links[] = array(
          'title' => (string) $tutorial['title'],
          'url'   => (string) $tutorial['url'],
        );
      }
    }

    $general_links = array();
    if ( ! empty( self::$documentation_link ) ) {
      $general_links[] = array(
        'title' => __( 'Documentation', 'food-and-drink-menu' ),
        'url'   => self::$documentation_link,
      );
    }
    if ( ! empty( self::$tutorials_link ) ) {
      $general_links[] = array(
        'title' => __( 'YouTube Tutorials', 'food-and-drink-menu' ),
        'url'   => self::$tutorials_link,
      );
    }
    if ( ! empty( self::$faq_link ) ) {
      $general_links[] = array(
        'title' => __( 'FAQ', 'food-and-drink-menu' ),
        'url'   => self::$faq_link,
      );
    }
    if ( ! empty( self::$wp_forum_support_link ) ) {
      $general_links[] = array(
        'title' => __( 'WP Forum Support', 'food-and-drink-menu' ),
        'url'   => self::$wp_forum_support_link,
      );
    }
    if ( ! empty( self::$support_center_link ) ) {
      $general_links[] = array(
        'title' => __( 'Support Center', 'food-and-drink-menu' ),
        'url'   => self::$support_center_link,
      );
    }

    $help_links = array();
    if ( ! empty( $tutorial_links ) ) { $help_links[ __( 'Tutorials', 'food-and-drink-menu' ) ] = $tutorial_links; }
    if ( ! empty( $general_links ) ) { $help_links[ __( 'General', 'food-and-drink-menu' ) ] = $general_links; }

    $items[] = array(
      'id'              => 'fdm_help',
      'title'           => __( 'Restaurant Menu Help', 'food-and-drink-menu' ),
      'description'     => ! empty( $page_details['description'] ) ? '<p>' . esc_html( $page_details['description'] ) . '</p>' : '',
      'help_links'      => $help_links,
      'source'          => array(
        'type' => 'plugin',
        'name' => 'Five Star Restaurant Menu and Food Ordering',
        'slug' => 'food-and-drink-menu',
      ),
      'target_callback' => array( __CLASS__, 'aiaa_target_callback' ),
      'priority'        => 20,
      'capability'      => 'manage_options',
      'icon'            => 'dashicons-editor-help',
    );

    return $items;
  }

  /**
   * AIAA advanced targeting callback.
   *
   * @param array $context
   * @param array $item
   * @return bool
   *
   * @since 5.1.0
   */
  public static function aiaa_target_callback( $context, $item ) {

    $context = is_array( $context ) ? $context : array();

    $screen_id = isset( $context['screen_id'] ) ? (string) $context['screen_id'] : '';
    $post_type = isset( $context['post_type'] ) ? (string) $context['post_type'] : '';
    $taxonomy  = isset( $context['taxonomy'] ) ? (string) $context['taxonomy'] : '';

    return self::aiaa_matches_context( $screen_id, $post_type, $taxonomy );
  }

  /**
   * Shared matcher for AIAA context.
   *
   * @param string $screen_id
   * @param string $post_type
   * @param string $taxonomy
   * @return bool
   *
   * @since 5.1.0
   */
  private static function aiaa_matches_context( $screen_id, $post_type, $taxonomy ) {

    if ( ! empty( $post_type ) && in_array( $post_type, self::$post_types, true ) ) { return true; }

    if ( ! empty( $taxonomy ) && in_array( $taxonomy, self::$taxonomies, true ) ) { return true; }

    if ( ! empty( $screen_id ) && ! empty( self::$additional_pages ) ) {

      foreach ( self::$additional_pages as $slug ) {

        if ( empty( $slug ) ) { continue; }

        if ( strpos( $screen_id, $slug ) !== false ) { return true; }
      }
    }

    return false;
  }

  /**
   * Handle ajax requests in admin area for logged out users.
   *
   * @since 5.1.0
   */
  public static function admin_nopriv_ajax() {

    wp_send_json_error(
      array(
        'error' => 'loggedout',
        'msg'   => sprintf( __( 'You have been logged out. Please %slogin again%s.', 'food-and-drink-menu' ), '<a href="' . wp_login_url( admin_url( 'edit.php?post_type=fdm-menu&page=fdm-dashboard' ) ) . '">', '</a>' ),
      )
    );
  }

  /**
   * Handle ajax requests where an invalid nonce is passed with the request.
   *
   * @since 5.1.0
   */
  public static function bad_nonce_ajax() {

    wp_send_json_error(
      array(
        'error' => 'badnonce',
        'msg'   => __( 'The request has been rejected because it does not appear to have come from this site.', 'food-and-drink-menu' ),
      )
    );
  }

  /**
   * Escapes PHP data being passed to JS, recursively.
   *
   * @since 5.1.0
   */
  public static function escape_js_recursive( $values ) {

    $return_values = array();

    foreach ( (array) $values as $key => $value ) {

      if ( is_array( $value ) ) {
        $value = fdmHelper::escape_js_recursive( $value );
      }
      elseif ( ! is_scalar( $value ) ) {
        continue;
      }
      else {
        $value = html_entity_decode( (string) $value, ENT_QUOTES, 'UTF-8' );
      }

      $return_values[ $key ] = $value;
    }

    return $return_values;
  }

  /**
   * Get page details for the current AIAA context.
   *
   * @param array $context
   * @return array
   */
  public static function get_page_details_for_context( $context ) {
    $context  = is_array( $context ) ? $context : array();
    $request  = isset( $context['request'] ) && is_array( $context['request'] ) ? $context['request'] : array();
    $page     = isset( $request['page'] ) ? sanitize_text_field( $request['page'] ) : '';
    $tab      = isset( $request['tab'] ) ? sanitize_text_field( $request['tab'] ) : '';
    $taxonomy = isset( $context['taxonomy'] ) ? sanitize_text_field( $context['taxonomy'] ) : '';
    $post_type = isset( $context['post_type'] ) ? sanitize_text_field( $context['post_type'] ) : '';
    $screen_id = isset( $context['screen_id'] ) ? sanitize_text_field( $context['screen_id'] ) : '';

    return self::get_page_details_by_values( $page, $tab, $taxonomy, $post_type, $screen_id );
  }

  /**
   * Resolve page details from the available context values.
   *
   * @param string $page
   * @param string $tab
   * @param string $taxonomy
   * @param string $post_type
   * @param string $screen_id
   * @return array
   */
  private static function get_page_details_by_values( $page, $tab, $taxonomy, $post_type, $screen_id ) {
    $page_details = array(
      'fdm-dashboard' => array(
        'description' => __( 'A quick overview of your Restaurant Menu plugin at a glance. See a summary of menus. Also provides access to upgrade options and helpful links to documentation, support, and tutorial videos to help you get the most out of the plugin.', 'food-and-drink-menu' ),
        'tutorials'   => array(
          array(
            'url'   => 'https://doc.fivestarplugins.com/plugins/food-and-drink-menu/user/',
            'title' => 'Getting Started'
          ),
          array(
            'url'   => 'https://doc.fivestarplugins.com/plugins/food-and-drink-menu/user/getting-started/setup',
            'title' => 'Walk-Through'
          ),
        )
      ),
      'fdm-orders' => array(
        'description' => __( 'View and manage all food orders placed through your restaurant menu. Filter orders by status — Received, Accepted, Preparing, or Ready. The overview table displays each order\'s ID, status, date, customer name, email, phone, items ordered, notes, custom fields, and payment amount.', 'food-and-drink-menu' ),
        'tutorials'   => array(
          array(
            'url'   => 'https://doc.fivestarplugins.com/plugins/food-and-drink-menu/user/ordering/',
            'title' => 'Ordering'
          ),
          array(
            'url'   => 'https://doc.fivestarplugins.com/plugins/food-and-drink-menu/user/ordering/enable',
            'title' => 'Enable and Configure Ordering'
          ),
          array(
            'url'   => 'https://doc.fivestarplugins.com/plugins/food-and-drink-menu/user/ordering/payment',
            'title' => 'Ordering Payment'
          ),
          array(
            'url'   => 'https://doc.fivestarplugins.com/plugins/food-and-drink-menu/user/ordering/notifications',
            'title' => 'Notifications'
          ),
          array(
            'url'   => 'https://doc.fivestarplugins.com/plugins/food-and-drink-menu/user/ordering/manage',
            'title' => 'Manage Orders'
          ),
        )
      ),
      'fdm-add-edit-order' => array(
        'description' => __( 'View and manage an individual order. Customer details include name, phone, and email. The order items table lists each item with quantity, price, and additional info, with the option to delete items or add new ones. The right-hand panel lets you update the order status, apply a discount, enter a payment amount, and view an automatically calculated order total with a full cost breakdown.', 'food-and-drink-menu' ),
        'tutorials'   => array(
          array(
            'url'   => 'https://doc.fivestarplugins.com/plugins/food-and-drink-menu/user/ordering/',
            'title' => 'Ordering'
          ),
          array(
            'url'   => 'https://doc.fivestarplugins.com/plugins/food-and-drink-menu/user/ordering/enable',
            'title' => 'Enable and Configure Ordering'
          ),
          array(
            'url'   => 'https://doc.fivestarplugins.com/plugins/food-and-drink-menu/user/ordering/payment',
            'title' => 'Ordering Payment'
          ),
          array(
            'url'   => 'https://doc.fivestarplugins.com/plugins/food-and-drink-menu/user/ordering/notifications',
            'title' => 'Notifications'
          ),
          array(
            'url'   => 'https://doc.fivestarplugins.com/plugins/food-and-drink-menu/user/ordering/manage',
            'title' => 'Manage Orders'
          ),
        )
      ),
      'edit-fdm-menu' => array(
        'description' => __( 'View and manage all your restaurant menus. Filter by status or date, and search by name. The overview table displays each menu\'s title, shortcode, assigned sections, and published date. Use the shortcode to embed any menu on a page or post.', 'food-and-drink-menu' ),
        'tutorials'   => array(
          array(
            'url'   => 'https://doc.fivestarplugins.com/plugins/food-and-drink-menu/user/menus/create-menu',
            'title' => 'Menus'
          ),
          array(
            'url'   => 'https://doc.fivestarplugins.com/plugins/food-and-drink-menu/user/menus/add-menu-to-page',
            'title' => 'Display Your Menu'
          ),
        )
      ),
      'fdm-menu' => array(
        'description' => __( 'Create or edit a restaurant menu. Add a title and description, then build the layout by assigning sections to your menu. Choose between a single or two-column layout by dragging sections into the first and second column areas. Sections can be reordered to control how the menu displays on the front end.', 'food-and-drink-menu' ),
        'tutorials'   => array(
          array(
            'url'   => 'https://doc.fivestarplugins.com/plugins/food-and-drink-menu/user/menus/create-menu',
            'title' => 'Menus'
          ),
          array(
            'url'   => 'https://doc.fivestarplugins.com/plugins/food-and-drink-menu/user/menus/add-menu-to-page',
            'title' => 'Display Your Menu'
          ),
        )
      ),
      'edit-fdm-menu-item' => array(
        'description' => __( 'View and manage all your restaurant menu items. Filter by menu section, status or date, and search by name. The overview table displays each menu item\'s title, price, menu section(s) and published date.', 'food-and-drink-menu' ),
        'tutorials'   => array(
          array(
            'url'   => 'https://doc.fivestarplugins.com/plugins/food-and-drink-menu/user/menus/items',
            'title' => 'Menu Items'
          ),
          array(
            'url'   => 'https://doc.fivestarplugins.com/plugins/food-and-drink-menu/user/menus/quick-price-edit',
            'title' => 'Quick Price Editing'
          ),
          array(
            'url'   => 'https://doc.fivestarplugins.com/plugins/food-and-drink-menu/user/menus/discounted-prices',
            'title' => 'Discounted Prices'
          ),
          array(
            'url'   => 'https://doc.fivestarplugins.com/plugins/food-and-drink-menu/user/menus/specials',
            'title' => 'Sales, Features and Specials'
          ),
          array(
            'url'   => 'https://doc.fivestarplugins.com/plugins/food-and-drink-menu/user/menus/sources',
            'title' => 'Sources'
          ),
        )
      ),
      'fdm-menu-item' => array(
        'description' => __( 'Create or edit a menu item. Add a title, description, and custom fields. Configure order add-ons with a name, default selection toggle, and extra cost. The right-hand panel holds the item price, with the option to add multiple price variations (e.g. 2 for $10, 4 for $12). Assign the item to one or more menu sections directly from this screen.', 'food-and-drink-menu' ),
        'tutorials'   => array(
          array(
            'url'   => 'https://doc.fivestarplugins.com/plugins/food-and-drink-menu/user/menus/items',
            'title' => 'Menu Items'
          ),
          array(
            'url'   => 'https://doc.fivestarplugins.com/plugins/food-and-drink-menu/user/menus/menu-icons',
            'title' => 'Menu Icons'
          ),
          array(
            'url'   => 'https://doc.fivestarplugins.com/plugins/food-and-drink-menu/user/menus/discounted-prices',
            'title' => 'Discounted Prices'
          ),
          array(
            'url'   => 'https://doc.fivestarplugins.com/plugins/food-and-drink-menu/user/menus/specials',
            'title' => 'Sales, Features and Specials'
          ),
          array(
            'url'   => 'https://doc.fivestarplugins.com/plugins/food-and-drink-menu/user/menus/sources',
            'title' => 'Sources'
          ),
        )
      ),
      'fdm-menu-section' => array(
        'description' => __( 'View and manage all your menu sections. The overview table displays each section\'s name, description, slug, and item count — click the count to filter and view all menu items assigned to that section. Add new sections directly from this screen by entering a name, slug, description, and optional image.', 'food-and-drink-menu' ),
        'tutorials'   => array(
        )
      ),
      'fdm-menu-item-flag' => array(
        'description' => __( 'Create and manage icons that can be assigned to menu items to indicate dietary or other attributes, such as Vegetarian or Kosher. Each icon has a name, slug, description, and a selected icon image. The overview table displays all icons with their name, description, slug, and how many items they are assigned to.', 'food-and-drink-menu' ),
        'tutorials'   => array(
          array(
            'url'   => 'https://doc.fivestarplugins.com/plugins/food-and-drink-menu/user/menus/menu-icons',
            'title' => 'Menu Icons'
          ),
        )
      ),
      'fdm-export' => array(
        'description' => __( 'Export your restaurant data with two options: export all menu items or export all orders. Each generates a downloadable file for reporting, backup, or migrating data to another system.', 'food-and-drink-menu' ),
        'tutorials'   => array(
        )
      ),
      'fdm-import' => array(
        'description' => __( 'Import menu items in bulk by uploading a spreadsheet file. A fast way to populate your menu without manual entry, useful when migrating from another system or setting up a large menu from scratch.', 'food-and-drink-menu' ),
        'tutorials'   => array(
        )
      ),
      'food-and-drink-menu-settings' => array(
        'description' => __( 'Core plugin configuration. Choose how menu CSS styles are applied, enable a section sidebar for front-end navigation, and control section image placement and descriptions. Disable pricing, price decimals, or structured microdata as needed. Set how product details are displayed — disabled, in a lightbox, or on a permalink page. Configure your currency symbol and its position. Enter a URL to generate a QR code for contactless menu access at your restaurant.', 'food-and-drink-menu' ),
        'tutorials'   => array(
          array(
            'url'   => 'https://doc.fivestarplugins.com/plugins/food-and-drink-menu/user/settings/google-maps-api-key',
            'title' => 'Set up your Google Maps API Key'
          ),
        )
      ),
      'food-and-drink-menu-settings-fdm-basic-settings' => array(
        'description' => __( 'Core plugin configuration. Choose how menu CSS styles are applied, enable a section sidebar for front-end navigation, and control section image placement and descriptions. Disable pricing, price decimals, or structured microdata as needed. Set how product details are displayed — disabled, in a lightbox, or on a permalink page. Configure your currency symbol and its position. Enter a URL to generate a QR code for contactless menu access at your restaurant.', 'food-and-drink-menu' ),
        'tutorials'   => array(
          array(
            'url'   => 'https://doc.fivestarplugins.com/plugins/food-and-drink-menu/user/settings/google-maps-api-key',
            'title' => 'Set up your Google Maps API Key'
          ),
        )
      ),
      'food-and-drink-menu-settings-fdm-advanced-tab' => array(
        'description' => __( 'Configure filtering and sorting options for your menu. Enable search by item name and/or description, allow price range filtering, and let visitors sort items by name, price, date, and more. Control related item display (none, automatic, or manual), and toggle advanced item features including flags, specials, discounted pricing, and source options.', 'food-and-drink-menu' ),
        'tutorials'   => array(
        )
      ),
      'food-and-drink-menu-settings-fdm-ordering-tab' => array(
        'description' => __( 'Configure the full ordering experience. Enable ordering, pause it temporarily, and set minimum order amounts, maximum received order limits, and auto-delete delays. Define required fields, weekly schedules, and holiday exceptions. Enable tipping, order time selection, customized item options, order progress display, ETA, delivery, and discount codes. Manage notification emails and SMS, customize notification templates, and configure payment via PayPal or Stripe including tax rate, test/live mode, SCA, and pay-in-store options.', 'food-and-drink-menu' ),
        'tutorials'   => array(
        )
      ),
      'food-and-drink-menu-settings-fdm-custom-fields-tab' => array(
        'description' => __( 'Create and manage custom fields to display additional information on menu items, such as nutritional info, allergens, or any other detail. Define each field\'s name, slug, type (short text, long text, dropdown, or checkboxes), and where it applies. Toggle the option to hide blank custom fields from the front end when no value has been entered.', 'food-and-drink-menu' ),
        'tutorials'   => array(
        )
      ),
      'food-and-drink-menu-settings-fdm-labelling-tab' => array(
        'description' => __( 'Rename any default text label used throughout the plugin to match your brand or language preferences. Covers menu item flags (On Sale, Special Offer, Featured, Sold Out), sidebar and filtering labels, sorting options, ordering cart text (Add to Cart, Quantity, Tax, Total, Check Out), checkout form fields, payment options, and order status messages. Useful for customizing the front-end experience without any code.', 'food-and-drink-menu' ),
        'tutorials'   => array(
          array(
            'url'   => 'https://doc.fivestarplugins.com/plugins/food-and-drink-menu/user/labelling/index',
            'title' => 'Labelling and Translation'
          ),
          array(
            'url'   => 'https://doc.fivestarplugins.com/plugins/food-and-drink-menu/user/labelling/translating',
            'title' => 'Create Your Own Translation'
          ),
        )
      ),
      'food-and-drink-menu-settings-fdm-styling-tab' => array(
        'description' => __( 'Customize the visual appearance of your menu and ordering cart. Control menu style, and fine-tune fonts, sizes, and colors for section titles, item names, descriptions, prices, images, separating lines, filtering area, and sidebar. For ordering, set the cart location (side or bottom), cart style, add-to-cart button colors, and order progress bar colors. Add your own custom CSS for deeper customization.', 'food-and-drink-menu' ),
        'tutorials'   => array(
        )
      ),
      'food-and-drink-menu-settings-fdm-api-tab' => array(
        'description' => __( 'Manage API access keys for the Five Star Restaurant Manager mobile app. Create keys with configurable permissions — View, and Update— to control what the app can access. Also displays the 10 most recent error log entries from the past week for troubleshooting connection or app-related issues.', 'food-and-drink-menu' ),
        'tutorials'   => array(
          array(
            'url'   => 'https://doc.fivestarplugins.com/plugins/food-and-drink-menu/user/fsrm/',
            'title' => 'Five Star Restaurant Manager Mobile App'
          ),
        )
      ),
    );

    $page = $page ? sanitize_text_field( $page ) : '';
    $tab = $tab ? sanitize_text_field( $tab ) : '';
    $taxonomy = $taxonomy ? sanitize_text_field( $taxonomy ) : '';
    $post_type = $post_type ? sanitize_text_field( $post_type ) : '';
    $screen_id = $screen_id ? sanitize_text_field( $screen_id ) : '';

    if ( $page && $tab && isset( $page_details[ $page . '-' . $tab ] ) ) { return $page_details[ $page . '-' . $tab ]; }

    if ( $page && isset( $page_details[ $page ] ) ) { return $page_details[ $page ]; }

    if ( $screen_id && isset( $page_details[ $screen_id ] ) ) { return $page_details[ $screen_id ]; }

    if ( $taxonomy && isset( $page_details[ $taxonomy ] ) ) { return $page_details[ $taxonomy ]; }

    if ( $post_type && $screen_id && strpos( $screen_id, 'edit-' ) === 0 && isset( $page_details[ 'edit-' . $post_type ] ) ) {
      return $page_details[ 'edit-' . $post_type ];
    }

    if ( $post_type && isset( $page_details[ $post_type ] ) ) { return $page_details[ $post_type ]; }

    return array( 'description' => '', 'tutorials' => array() );
  }
}

add_action( 'plugins_loaded', array( 'fdmHelper', 'aiaa_add_filter' ), 20 );

}
