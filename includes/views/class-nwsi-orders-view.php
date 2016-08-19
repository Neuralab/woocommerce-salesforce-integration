<?php
if ( !defined( "ABSPATH" ) ) {
  exit;
}

if ( !class_exists( "NWSI_Orders_View" ) ) {
  class NWSI_Orders_View {

    /**
     * Class constructor
     */
    public function __construct() {
      add_action( "add_meta_boxes", array( $this, "add_sf_order_meta_box" ) );

      add_filter( "manage_edit-shop_order_columns", array( $this, "manage_orders_columns" ), 10, 1 );
      add_action( "manage_shop_order_posts_custom_column", array( $this, "order_column_salesforce_sync" ), 10, 2 );

      add_action( "save_post", array( $this, "process_order_sync_request" ), 10, 1 );

    }

    /**
     * Check if user can sync order and send the request to Salesforce API
     * @param int $order_id
     */
    public function process_order_sync_request( $order_id ) {
      $nonce_name   = isset( $_POST["nwsi_sync_product_nonce"] ) ? $_POST["nwsi_sync_product_nonce"] : "";
      $nonce_action = "nwsi_sync_product";

      if ( !$_POST["salesforce_sync_request"] ) {
        return;
      }
      if ( !isset( $nonce_name ) || !wp_verify_nonce( $nonce_name, $nonce_action ) ) {
          return;
      }
      if ( !current_user_can( "edit_post", $order_id ) ) {
          return;
      }
      if ( wp_is_post_autosave( $order_id ) || wp_is_post_revision( $order_id ) ) {
          return;
      }

      require_once ( plugin_dir_path( __FILE__ ) . "../controllers/core/class-nwsi-salesforce-worker.php" );
      $worker = new NWSI_Salesforce_Worker();
      $worker->handle_order( $order_id );

    }

    /**
     * Create new sync column in orders preview
     * @param array $columns
     * @return array
     */
    public function manage_orders_columns( $columns ) {
      $new_columns = array();
      foreach( $columns as $key => $value ) {
        if ( $key == "order_actions" ) {
          $new_columns["salesforce_sync"] = __( "Salesforce sync status", "woocommerce-integration-nwsi" );
        }
        $new_columns[ $key ] = $value;
      }

      return $new_columns;
    }

    /**
     * Insert value for salesforce sync column in orders preview
     * @param string  $column
     * @param int     $post_id
     */
    public function order_column_salesforce_sync( $column, $post_id ) {
      if ( $column == "salesforce_sync" ) {
        $status = get_post_meta( $post_id, "_sf_sync_status", true );
        if ( empty( $status ) ) {
          echo "none";
        } else {
          echo $status;
        }
      }
    }

    /**
     * Create custom meta box in order preview for salesforce sync status
     */
    public function add_sf_order_meta_box() {
      add_meta_box(
        "woocommerce_display_sf_order_meta_box_fields",
        __( "Salesforce Sync", "woocommerce-integration-nwsi" ),
        array( $this, "display_sf_order_meta_box_fields" ),
        "shop_order",
        "side",
        "default"
      );
    }

    /**
     * Echo HTML for meta box in order preview for salesforce sync status
     */
    public function display_sf_order_meta_box_fields() {
      global $post;

      $status = get_post_meta( $post->ID, "_sf_sync_status", true );
      if ( empty( $status ) ) {
        $status = "none";
      }
      echo "<span class='nwsi-sf-sync-metabox-column-name'>" . __( "Status", "woocommerce-integration-nwsi" ) . ":</span> " . $status . "<br/>";

      if ( $status == "failed" ) {
        $error_messages = get_post_meta( $post->ID, "_sf_sync_error_message", true );

        try {
          $error_messages = json_decode( $error_messages );
          $counter = 1;
          foreach( $error_messages as $error_message ) {
            $error_messages_txt .= $counter++ . ". " . $error_message . "\n";
          }
        } catch( Exception $ex ) {
          $error_messages_txt = "";
        }

        echo "<span class='nwsi-sf-sync-metabox-column-name'>" . __( "Error message", "woocommerce-integration-nwsi" ) . ":</span> " . "<br/>";
        echo "<textarea style='width:100%;min-height:200px;'>" . $error_messages_txt . "</textarea>";
      }

      if ( $status == "failed" || $status == "none" ) {
        wp_nonce_field( "nwsi_sync_product", "nwsi_sync_product_nonce" );
        echo "<br/>";
        echo "<input type='submit' name='salesforce_sync_request' class='button button-primary' value='" . __( "Save and sync order", "woocommerce-integration-nwsi" ) . "' />";
      }

    }

  }
}
