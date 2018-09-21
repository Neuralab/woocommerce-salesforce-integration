<?php
if ( !defined( "ABSPATH" ) ) {
  exit;
}

if ( !class_exists( "WC_Product" ) ) {
  error_log( "WC_Product class missing! Check if WooCommerce plugin is installed properly." );
  return;
}

require_once( "interface-nwsi-model.php" );

if ( !class_exists( "NWSI_Product_Model" ) ) {
  /**
   * Class which extends WC_Product and provides methods for easier data access.
   *
   * @version 0.9.2
   */
  class NWSI_Product_Model extends WC_Product implements NWSI_Model {

    private $order_product_meta_data;

    /**
    * Class constructor.
    *
    * @override
    * @see https://docs.woocommerce.com/wc-apidocs/class-WC_Product.html
    * @param int|WC_Product $product  Defaults to empty string.
    */
    public function __construct( $product = "" ) {
      parent::__construct( $product );
    }

    /**
    * Set product meta data.
    *
    * @param array meta_data
    */
    public function set_order_product_meta_data( $meta_data ) {
      $this->order_product_meta_data = $meta_data;
    }

    /**
    * Return product properties from latest product
    * @return array
    */
    public function get_product_meta_keys() {
      require_once( NWSI_DIR_PATH . "includes/controllers/core/class-nwsi-db.php" );

      $db = new NWSI_DB();
      $meta_keys = $db->get_product_meta_keys();

      if ( empty( $items ) ) {
        // fallback if there's no products in DB
        $items = $this->get_default_product_meta_keys();
      }
      return $meta_keys;
    }

    /**
    * Return product meta keys.
    *
    * @since 0.9.1
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
      if ( has_filter( "nwsi_include_product_keys_from_database" ) ) {
        $include_db_keys = (bool) apply_filters( "nwsi_include_product_keys_from_database" );
      }

      if ( $include_db_keys ) {
        // combine with product meta keys from the database
        require_once( NWSI_DIR_PATH . "includes/controllers/core/class-nwsi-db.php" );
        $db   = new NWSI_DB();
        $keys = array_merge( $data_keys, $db->get_product_meta_keys() );
      } else {
        $keys = $data_keys;
      }

      $unique_keys = array_unique( $keys );
      sort( $unique_keys, SORT_STRING );

      if ( has_filter( "nwsi_product_property_keys" ) ) {
        $unique_keys = (array) apply_filters( "nwsi_product_property_keys", $unique_keys );
      }

      return $unique_keys;
    }

    /**
    * Return property value.
    *
    * @since 0.9.1
    * @param string $property_name
    * @return string
    */
    public function get( $property_name ) {
      $value = null;
      if ( method_exists( $this, "get_" . $property_name ) ) {
        $value = $this->{"get_" . $property_name}();
      }

      if ( has_filter( "nwsi_get_product_property_key_" . $property_name ) ) {
        return apply_filters( "nwsi_get_product_property_key_" . $property_name, $value, $this );
      } else {
        return $value;
      }
    }

  }
}
?>
