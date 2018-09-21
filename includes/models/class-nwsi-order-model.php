<?php
if ( !defined( "ABSPATH" ) ) {
  exit;
}

if ( !class_exists( "WC_Order" ) ) {
  error_log( "WC_Order class missing! Check if WooCommerce plugin is installed properly." );
  return;
}

require_once( "interface-nwsi-model.php" );

if ( !class_exists( "NWSI_Order_Model" ) ) {
  /**
   * Class which extends WC_Order and provides methods for easier data access.
   *
   * @version 0.9.2
   */
  class NWSI_Order_Model extends WC_Order implements NWSI_Model {

    /**
    * Class constructor.
    *
    * @override
    * @see https://docs.woocommerce.com/wc-apidocs/class-WC_Order.html
    * @param int|WC_Order $order  Defaults to empty string.
    */
    public function __construct( $order = "" ) {
      parent::__construct( $order );
    }

    /**
    * Return order meta keys which are collected from the WC_Order class
    *
    * @since 0.1
    * @return array
    */
    public function get_property_keys() {
      $data      = $this->get_data();
      $data_keys = $this->get_data_keys();

      // keys which hold subarrays in $data
      $parent_keys = array( "shipping", "billing" );
      for ( $i = 0; $i < count( $data_keys ) ; $i++ ) {
        foreach ( $parent_keys as $parent_key ) {
          if ( isset( $data_keys[$i] ) && $parent_key === $data_keys[$i] ) {
            unset( $data_keys[$i] );
            foreach ( $data[$parent_key] as $child_key => $child_value ) {
              array_push( $data_keys, $parent_key . "_" . $child_key );
            }
          }
        }
      }

      $include_db_keys = false;
      if ( has_filter( "nwsi_include_order_keys_from_database" ) ) {
        $include_db_keys = (bool) apply_filters( "nwsi_include_order_keys_from_database" );
      }

      if ( $include_db_keys ) {
        // combine with order meta keys from the database
        require_once( NWSI_DIR_PATH . "includes/controllers/core/class-nwsi-db.php" );
        $db   = new NWSI_DB();
        $keys = array_merge( $data_keys, $db->get_order_meta_keys() );
      } else {
        $keys = $data_keys;
      }

      $unique_keys = array_unique( $keys );
      sort( $unique_keys, SORT_STRING );

      if ( has_filter( "nwsi_order_property_keys" ) ) {
        $unique_keys = (array) apply_filters( "nwsi_order_property_keys", $unique_keys );
      }

      return $unique_keys;
    }

    /**
    * Return property value.
    *
    * @since 0.1
    * @param string $property_name
    * @return string
    */
    public function get( $property_name ) {
      $value = null;
      if ( method_exists( $this, "get_" . $property_name ) ) {
        $value = $this->{"get_" . $property_name}();
      }

      if ( has_filter( "nwsi_get_order_property_key_" . $property_name ) ) {
        return apply_filters( "nwsi_get_order_property_key_" . $property_name, $value, $this );
      } else {
        return $value;
      }
    }

  }
}
