<?php
/**
* Plugin Name: Neuralab WooCommerce SalesForce Integration
* Plugin URI: https://github.com/Neuralab/WooCommerce-Salesforce-integration
* Description: Syncing engine for all sort of datasets and configurations.
* Version: 0.9.2
* Author: Neuralab
* Author URI: https://neuralab.net
* Developer: matej@neuralab
* Text Domain: woocommerce-integration-nwsi
*
* WC requires at least: 3.3
* WC tested up to: 3.3.4
*
* License: MIT
* Requires at least: 4.9
*/

if ( !defined( "ABSPATH" ) ) {
  exit;
}


if ( !function_exists( "nwsi_is_woocommerce_active" ) ) {
  /**
  * Return true if Woocommerce plugin is active
  * @return boolean
  */
  function nwsi_is_woocommerce_active() {
    if ( in_array( "woocommerce/woocommerce.php", apply_filters( "active_plugins", get_option( "active_plugins" ) ) ) ) {
      return true;
    }
    return false;
  }
}

if ( !function_exists( "nwsi_admin_notice_missing_woocommerce" ) ) {
  /**
  * Echo admin notice HTML for missing WooCommerce plugin
  */
  function nwsi_admin_notice_missing_woocommerce() {
    global $current_screen;
    if( $current_screen->parent_base === "plugins" ) {
      ?>
      <div class="notice notice-error">
        <p><?php _e( "Please install and activate <a href='https://woocommerce.com/' target='_blank'>WooCommerce</a> before activating the Neuralab WooCommerce SalesForce Integration plugin!", "woocommerce-integration-nwsi" ); ?></p>
      </div>
      <?php
    }
  }
}

if ( nwsi_is_woocommerce_active() ) {
  if ( !class_exists( "NWSI_Main" ) ) {
    /**
     * Main plugin's class. Holds installation and init methods.
     */
    class NWSI_Main {

      /**
       * Current version of the plugin.
       *
       * @var string
       */
      const VERSION = "0.9.2";

      /**
       * Singleton instance.
       *
       * @var NWSI_Main
       */
      protected static $instance = null;

      /**
       * @var NWSI_Salesforce_Worker
       */
      private $worker;

      /**
       * Class constructor, initialize essential classes and hooks.
       */
      protected function __construct() {
        $this->define_plugin_constants();

        require_once( "includes/controllers/core/class-nwsi-salesforce-object-manager.php" );
        require_once( "includes/controllers/core/class-nwsi-salesforce-worker.php" );
        require_once( "includes/controllers/utilites/class-nwsi-utility.php" );
        require_once( "includes/views/class-nwsi-settings.php" );

        add_filter( "woocommerce_integrations", array( $this, "add_integration_section" ) );

        if ( is_admin() ) {
          require_once( "includes/views/class-nwsi-orders-view.php" );
          $orders_view = new NWSI_Orders_View();
          $orders_view->register_hooks();

          //TODO: Find a better way of doing this
          add_action('admin_enqueue_scripts', function() {
            wp_enqueue_style( "nwsi-settings-style", plugins_url( "/includes/style/nwsi-settings.css", __FILE__ ) );
          });
        }

        $this->worker = new NWSI_Salesforce_Worker();
        // add_action( "woocommerce_checkout_order_processed", array( $this, "process_order" ), 10, 1 );
        add_action( "woocommerce_thankyou", array( $this, "process_order" ), 90, 1 );

      }

      /**
       * Define all the constants used in plugin.
       */
      private static function define_plugin_constants() {
        if ( !defined( "NWSI_DIR_NAME" ) ) {
          define( "NWSI_DIR_NAME", basename( __DIR__ ) );
        }

        if ( !defined( "NWSI_DIR_PATH" ) ) {
          define( "NWSI_DIR_PATH", plugin_dir_path( __FILE__ ) );
        }

        if ( !defined( "NWSI_DIR_URL" ) ) {
          define( "NWSI_DIR_URL", plugins_url( "/", __FILE__ ) );
        }
      }

      /**
       * Process order.
       * @param int $order_id
       */
      public function process_order( $order_id ) {
        if ( !empty( get_option( "woocommerce_nwsi_automatic_order_sync" ) ) ) {
          $this->worker->process_order( $order_id );
        }
      }

      /**
       * Create plugins table and triggers in DB.
       */
      public static function install() {
        if ( !current_user_can( "activate_plugins" ) ) {
          return;
        }

        NWSI_Main::define_plugin_constants();
        update_option( "woocommerce_nwsi_login_url", "https://login.salesforce.com" );

        require_once( "includes/controllers/core/class-nwsi-db.php" );
        $db = new NWSI_DB();
        $db->create_relationship_table();

        if ( $db->is_relationship_table_empty() ) {
          // insert default relationships
          require_once( "includes/controllers/utilites/class-nwsi-utility.php" );
          $utility = new NWSI_Utility();
          $relationships = $utility->load_from_file( "default_relationships.json", "data" );

          if ( !empty( $relationships ) ) {
            foreach( $relationships as $relationship ) {
              $db->save_new_relationship( $relationship->from_object, $relationship->from_object_label,
              $relationship->to_object, $relationship->to_object_label, $relationship->relationships,
              $relationship->required_sf_objects, $relationship->unique_sf_fields );
            }
          }
        }
      }

      /**
       * Delete plugin tables and related WP options.
       */
      public static function uninstall() {
        if ( !current_user_can( "activate_plugins" ) ) {
          return;
        }

        delete_option( "woocommerce_nwsi_settings" );
        delete_option( "woocommerce_nwsi_access_token" );
        delete_option( "woocommerce_nwsi_refresh_token" );
        delete_option( "woocommerce_nwsi_instance_url" );
        delete_option( "woocommerce_nwsi_automatic_order_sync" );
        delete_option( "woocommerce_nwsi_connection_hash" );
        delete_option( "woocommerce_nwsi_login_url" );

        require_once( "includes/controllers/core/class-nwsi-db.php" );
        $db = new NWSI_DB();
        $db->delete_relationship_table();

        // clear any cached data that has been removed
        wp_cache_flush();
      }

      /**
       * Return integrations array with new section element. Needed for
       * woocommerce_integrations filter hook.
       *
       * @param array $integrations
       * @return array
       */
      public function add_integration_section( $integrations ) {
        $integrations[] = "NWSI_Settings";
        return $integrations;
      }

      /**
       * Return class instance.
       *
       * @return NWSI_Main
       */
      public static function get_instance() {
        if ( is_null( self::$instance ) ) {
          self::$instance = new self;
        }
        return self::$instance;
      }

      /**
       * Cloning is forbidden.
       */
      public function __clone() {
        _doing_it_wrong( __FUNCTION__, __( "Cloning is forbidden!", "woocommerce-integration-nwsi" ), "4.0" );
      }

      /**
       * Unserializing instances of this class is forbidden.
       */
      public function __wakeup() {
        _doing_it_wrong( __FUNCTION__, __( "Unserializing instances is forbidden!", "woocommerce-integration-nwsi" ), "4.0" );
      }

    }
  }

  register_activation_hook( __FILE__, array( "NWSI_Main", "install" ) );
  register_uninstall_hook( __FILE__, array( "NWSI_Main", "uninstall" ) );

  add_action( "plugins_loaded", array( "NWSI_Main", "get_instance" ), 0 );
} else {
  add_action( "admin_notices", "nwsi_admin_notice_missing_woocommerce" );
}
