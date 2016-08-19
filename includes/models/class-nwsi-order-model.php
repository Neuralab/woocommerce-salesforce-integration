<?php
if ( !defined( "ABSPATH" ) ) {
  exit;
}

if ( !class_exists( "NWSI_Model" ) ) {
  require_once( "interface-nwsi-model.php" );
}

if ( !class_exists( "NWSI_Order_Model" ) ) {
  class NWSI_Order_Model extends WC_Order implements NWSI_Model {

    private $order_properties;
    private $post_properties;

    /**
    * Class constructor
    * @override
    */
    public function __construct( $order = "" ) {

      if ( !empty( $order ) ) {
        parent::init( $order );
      }

      $this->order_properties = $this->get_order_meta_keys();
      // needed data from WP_Post (WC_Order containts post)
      $this->post_properties = ["customer_message", "order_date", "modified_date", "order_type"];
    }

    /**
    * Return order meta keys from latest order
    * @return array
    */
    public function get_order_meta_keys() {

      require_once( plugin_dir_path( __FILE__ )  . "../controllers/core/class-nwsi-db.php" );

      $db = new NWSI_DB();
      $items = $db->get_order_meta_keys();

      if ( empty( $items ) ) {
        // fallback if there's no orders in DB
        $items = $this->get_default_meta_keys();
      }

      $items = $this->get_default_meta_keys();

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
    * Return WC_Order properties
    * @return array
    */
    public function get_property_keys() {
      return array_merge( $this->order_properties, $this->post_properties );
    }

    /**
    * Return property value
    * @param string $property_name
    * @return string
    */
    public function get( $property_name ) {
      switch( $property_name ) {
        case "customer_message":
          return $this->customer_message;
        case "order_date":
          return $this->order_date;
        case "modified_date":
          return $this->modified_date;
        case "order_type":
          return $this->order_type;
        default:
          return parent::__get( $property_name );
      }
    }

      /**
      * Return default meta_keys (use as fallback, dirty!)
      * @return array
      */
      private function get_default_meta_keys() {
        $utility = new NWSI_Utility();
        $response = $utility->load_from_file( "default_order_meta_keys.json", "data" );
        if ( empty( $response ) ) {
          return array();
        }
        return $response;
      }

    }
}
