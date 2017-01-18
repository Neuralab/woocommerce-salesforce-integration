<?php
/**
* Plugin Name: Neuralab WooCommerce SalesForce Integration
* Plugin URI: https://neuralab.net
* Description: WooCommerce SalesForce Integration
* Version: 0.9
* Author: Neuralab
* Author URI: https://neuralab.net
* License:
* Requires at least: 4.4
*/

if ( !defined( "ABSPATH" ) ) {
  exit;
}

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

/**
* Echo admin notice HTML for missing WooCommerce plugin
*/
function nwsi_admin_notice_missing_woocommerce() {
  global $current_screen;

  if( $current_screen->parent_base == "plugins" ) {
    ?>
    <div class="notice notice-error">
      <p><?php _e( "Please install and activate <a href='http://www.woothemes.com/woocommerce/' target='_blank'>WooCommerce</a> before activating the WooCommerce SalesForce Integration!", "woocommerce-integration-nwsi" ); ?></p>
    </div>
    <?php
  }
}

if ( nwsi_is_woocommerce_active() ) {
  if ( !class_exists( "WC_Salesforce_Integration" ) ) {
    class NWSI_Main {

      const VERSION = "0.9";
      protected static $instance = null;
      private $worker;

      /**
       * Class constructor
       */
      protected function __construct() {
        if ( !defined( "NWSI_FOLDER_NAME" ) ) {
          define( NWSI_FOLDER_NAME, basename( __DIR__ ) );
        }

        require_once( "includes/controllers/core/class-nwsi-salesforce-object-manager.php" );
        require_once( "includes/controllers/core/class-nwsi-salesforce-worker.php" );
        require_once( "includes/controllers/utilites/class-nwsi-utility.php" );
        require_once( "includes/views/class-nwsi-settings.php" );

        add_filter( "woocommerce_integrations", array( $this, "add_integration_section" ) );

        if ( is_admin() ) {
          require_once( "includes/views/class-nwsi-orders-view.php" );
          new NWSI_Orders_View();
            //TODO: Find a better way of doing this
           add_action('admin_enqueue_scripts', function() {
                wp_enqueue_style( "nwsi-settings-style", plugins_url( "/includes/style/nwsi-settings.css", FILE ) );
            });
        }

        $this->worker = new NWSI_Salesforce_Worker();
        // add_action( "woocommerce_checkout_order_processed", array( $this, "process_order" ), 10, 1 );
        add_action( "woocommerce_thankyou", array( $this, "process_order" ), 90, 1 );

      }

      /**
       * Process order
       * @param int $order_id
       */
      public function process_order( $order_id ) {
        if ( !empty( get_option( "woocommerce_nwsi_automatic_order_sync" ) ) ) {
          $this->worker->process_order( $order_id );
        }
      }

      /**
       * Create plugins table and triggers in DB
       */
      public static function install() {
        if ( !current_user_can( "activate_plugins" ) ) {
  	      return;
        }
        if ( !defined( "NWSI_FOLDER_NAME" ) ) {
          define( NWSI_FOLDER_NAME, basename( __DIR__ ) );
        }

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
       * Delete plugins table and related WP options
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

        require_once( "includes/controllers/core/class-nwsi-db.php" );
        $db = new NWSI_DB();
        $db->delete_relationship_table();

        // clear any cached data that has been removed
        wp_cache_flush();
      }

      /**
       * Return integrations array with new section element.
       * Needed for woocommerce_integrations filter hook
       * @param array $integrations
       * @return array
       */
      public function add_integration_section( $integrations ) {
        $integrations[] = "NWSI_Settings";
        return $integrations;
      }

      /**
       * Return class instance
       * @return NWSI_Main
       */
      public static function get_instance() {
        if ( is_null( self::$instance ) ) {
          self::$instance = new self;
        }
        return self::$instance;
      }

      /**
       * Cloning is forbidden
       */
      public function __clone() {
        _doing_it_wrong( __FUNCTION__, __( "Cloning is forbidden!", "woocommerce-integration-nwsi" ), "4.0" );
      }

      /**
       * Unserializing instances of this class is forbidden
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
