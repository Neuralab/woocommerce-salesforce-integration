<?php
if ( !defined( "ABSPATH" ) ) {
  exit;
}

if ( !class_exists( "NWSI_Model" ) ) {
  require_once( "interface-nwsi-model.php" );
}

if ( !class_exists( "NWSI_Order_Product_Model" ) ) {
  class NWSI_Order_Product_Model extends WC_Product implements NWSI_Model {

    private $order_product_property_keys;
    private $product_property_keys;
    private $post_property_keys;

    private $order_product_meta_data;

    /**
    * Class constructor
    * @override
    */
    public function __construct( $product = "" ) {

      if ( !empty( $product ) ) {
        parent::__construct( $product );
      }

      // magic WC_Product properties
      $this->product_property_keys = $this->get_product_meta_keys();
      // Order product meta
      $this->order_product_property_keys = $this->get_order_product_meta_keys();

      // WC_Product contains WP_Post which contains interesting data from SF
      // name         = post_title
      // description  = post_content
      // date         = post_date
      $this->post_property_keys = [ "name", "description", "date" ];
    }

    /**
    * Set product meta data
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
      // properties not needed for integration
      $except = ["_edit_lock", "_edit_last", "_product_image_gallery", "_is_variable"];

      require_once( plugin_dir_path( __FILE__ ) . "../controllers/core/class-nwsi-db.php" );

      $db = new NWSI_DB();
      $items = $db->get_product_meta_keys();

      if ( empty( $items ) ) {
        // fallback if there's no products in DB
        $items = $this->get_default_product_meta_keys();
      }

      $meta_keys = array();
      foreach( $items as $item ) {
        if ( in_array( $item->meta_key, $except ) ) {
          continue;
        }
        $pos = strpos( $item->meta_key, "_" );
        if ( $pos !== false ) {
          array_push( $meta_keys, substr_replace( $item->meta_key, "", $pos, strlen( "_" ) ) );
        }
      }

      return $meta_keys;
    }

    /**
    * Return order product meta keys from latest order
    * @return array
    */
    public function get_order_product_meta_keys() {

      require_once( plugin_dir_path( __FILE__ ) . "../controllers/core/class-nwsi-db.php" );

      $db = new NWSI_DB();
      $items = $db->get_order_product_meta_keys();

      if ( empty( $items ) ) {
        // fallback if there's no products in DB
        $items = $this->get_default_order_product_meta_keys();
      }

      $meta_keys = array();
      foreach( $items as $item ) {
        $pos = strpos( $item->meta_key, "_" );
        if ( $pos !== false ) {
          array_push( $meta_keys, substr_replace( $item->meta_key, "", $pos, strlen( "_" ) ) );
        }
      }

      return $meta_keys;
    }

    /**
    * Return Product properties
    */
    public function get_property_keys() {
      return array_merge( $this->post_property_keys, $this->product_property_keys, $this->order_product_property_keys );
    }

    /**
    * Return property value
    * @param string $property_name
    * @return string
    */
    public function get( $property_name ) {

      switch( $property_name ) {
        case "name":
          return $this->post->post_title;
        case "description":
          return $this->post->post_content;
        case "date":
          return $this->post->post_date;
        case "sale_price":
          return ( !empty( parent::__get( $property_name ) ) ) ? parent::__get( $property_name ) : parent::__get( "regular_price" );
        default:
          $response = parent::__get( $property_name );
          if ( empty( $response ) ) {
            $response = $this->order_product_meta_data[ "_" . $property_name ][0];
          }
          return $response;
      }
    }

    /**
    * Return default product meta_keys (use as fallback, dirty!)
    * @return array
    */
    private function get_default_product_meta_keys() {
      $utility = new NWSI_Utility();
      $response = $utility->load_from_file( "default_product_meta_keys.json", "data" );
      if ( empty( $response ) ) {
        return array();
      }
      return $response;
    }

    /**
    * Return default order product meta_keys (use as fallback, dirty!)
    * @return array
    */
    private function get_default_order_product_meta_keys() {
      $utility = new NWSI_Utility();
      $response = $utility->load_from_file( "default_order_product_meta_keys.json", "data" );
      if ( empty( $response ) ) {
        return array();
      }
      return $response;
    }

  }
}
?>
