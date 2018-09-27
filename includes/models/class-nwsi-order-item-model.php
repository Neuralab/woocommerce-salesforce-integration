<?php
if ( !defined( "ABSPATH" ) ) {
  exit;
}

if ( !class_exists( "WC_Order_Item" ) ) {
  error_log( "WC_Order_Item class missing! Check if WooCommerce plugin is installed properly." );
  return;
}

require_once( "interface-nwsi-model.php" );

if ( !class_exists( "NWSI_Order_Item_Model" ) ) {
  /**
   * Class which extends WC_Order_Item and provides methods for easier data access.
   *
   * @version 0.9.2
   */
  class NWSI_Order_Item_Model extends WC_Order_Item implements NWSI_Model {

    /**
    * Class constructor.
    *
    * @override
    * @see https://docs.woocommerce.com/wc-apidocs/class-WC_Order_Item.html for
    * @param int|WC_Order_Item|array $order_item  Defaults to 0.
    */
    public function __construct( $order_item = 0 ) {
      parent::__construct( $order_item );
    }

    /**
    * Return order meta keys which are collected from the WC_Order_Item class.
    *
    * @since 0.9.2
    * @return array
    */
    public function get_property_keys() {
      $data      = $this->get_data();
      $data_keys = $this->get_data_keys();

      foreach ( $data_keys as $data_key ) {
        if ( isset( $data[$data_key] ) && is_array( $data[$data_key] ) ) {
          $data_key_index = array_search( $data_key, $data_keys );
          if ( $data_key_index !== false ) {
            unset( $data_keys[$data_key_index] );
          }
        }
      }

      $include_db_keys = false;
      if ( has_filter( "nwsi_include_order_item_keys_from_database" ) ) {
        $include_db_keys = (bool) apply_filters( "nwsi_include_order_item_keys_from_database" );
      }

      if ( $include_db_keys ) {
        // combine with order meta keys from the database
        require_once( NWSI_DIR_PATH . "includes/controllers/core/class-nwsi-db.php" );
        $db   = new NWSI_DB();
        $keys = array_merge( $data_keys, $this->get_order_item_meta_keys() );
      } else {
        $keys = $data_keys;
      }

      $unique_keys = array_unique( $keys );
      sort( $unique_keys, SORT_STRING );

      if ( has_filter( "nwsi_order_item_property_keys" ) ) {
        $unique_keys = (array) apply_filters( "nwsi_order_item_property_keys", $unique_keys );
      }

      return $unique_keys;
    }

    /**
    * Return property value.
    *
    * @since 0.9.2
    * @param string $property_name
    * @return string
    */
    public function get( $property_name ) {
      $value = null;
      if ( method_exists( $this, "get_" . $property_name ) ) {
        $value = $this->{"get_" . $property_name}();
      }

      if ( has_filter( "nwsi_get_order_item_property_key_" . $property_name ) ) {
        return apply_filters( "nwsi_get_order_item_property_key_" . $property_name, $value, $this );
      } else {
        return $value;
      }
    }

    /**
    * Return WC_Order_Item properties from order item entry in DB.
    *
    * @return array
    */
    public function get_order_item_meta_keys() {
      global $wpdb;

      $query = "SELECT DISTINCT ( meta_key ) FROM " . $wpdb->prefix . "woocommerce_order_itemmeta";

      return $wpdb->get_results( $query );
    }

  }
}
