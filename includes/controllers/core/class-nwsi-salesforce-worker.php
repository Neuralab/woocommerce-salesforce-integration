<?php
if ( !defined( "ABSPATH" ) ) {
  exit;
}

if ( !class_exists( "WP_Async_Request" ) ) {
  require_once( NWSI_DIR_PATH . "includes/libs/wp-background-processing/wp-async-request.php" );
}

if ( !class_exists( "NWSI_Salesforce_Worker" ) ) {
  class NWSI_Salesforce_Worker extends WP_Async_Request {

    private $db;
    private $sf;

    protected $prefix = "nwsi";
    protected $action = "process_wc_order";

    /**
    * Class constructor
    */
    public function __construct() {
      parent::__construct();

      require_once( NWSI_DIR_PATH . "includes/models/class-nwsi-order-model.php" );
      require_once( NWSI_DIR_PATH . "includes/models/class-nwsi-product-model.php" );
      require_once( NWSI_DIR_PATH . "includes/controllers/core/class-nwsi-db.php" );

      $this->db = new NWSI_DB();
      $this->sf = new NWSI_Salesforce_Object_Manager();
    }

    /**
    * Init async order processing procedure
    * @param int $order_id
    */
    public function process_order( $order_id ) {
      $this->data( array( "order_id" => $order_id ) );
      $this->dispatch();
    }
    /**
    * Extract order id from $_POST, process order and send data to Salesforce
    * @override
    */
    // protected function handle() {
    public function handle() {
      if ( array_key_exists( "order_id", $_POST ) && !empty( $_POST["order_id"] ) ) {
        $order_id = $_POST["order_id"];
      } else {
        return;
      }
      $this->handle_order( $order_id );
    }

    /**
     * Process order and send data to Salesforce.
     *
     * @param int $order_id
     */
    public function handle_order( int $order_id ) {
      $is_success = true;
      $error_message = array();
      $relationships = $this->db->get_active_relationships();

      if ( empty( $relationships ) ) {
        update_post_meta( $order_id, "_sf_sync_status", "failed" );
        array_push( $error_message, "NWSI: No defined relationships." );
        update_post_meta( $order_id, "_sf_sync_error_message", json_encode( $error_message ) );
        return;
      }

      $relationships = $this->prioritize_relationships( $relationships );

      // contains ids of created objects
      $response_ids = array();

      $order    = new NWSI_Order_Model( $order_id );
      $products = $this->get_products_from_order( $order );

      foreach( $relationships as $relationship ) {
        // get relationship connections
        $connections = json_decode( $relationship->relationships );

        if ( $relationship->from_object === "Order" ) {
          // process order
          $values = $this->get_values( $connections, $order );
          $this->set_dependencies(
            $relationship->to_object, $values,
            json_decode( $relationship->required_sf_objects ),
            $response_ids, $relationship->from_object
          );

          if ( !empty( $values ) ) {
            $response = $this->send_to_salesforce(
              $relationship->to_object, $values,
              json_decode( $relationship->unique_sf_fields ), $response_ids
            );

            if ( !$response["success"] ) {
              $is_success = false;
              array_push( $error_message, $response["error_message"] );
              break; // no need to continue
            }
          }

        } else if ( $relationship->from_object === "Order Product" ) {
          $i = 0;
          foreach( $products as $product ) {
            $values = $this->get_values( $connections, $product );
            $this->set_dependencies( $relationship->to_object, $values,
            json_decode( $relationship->required_sf_objects ), $response_ids, $relationship->from_object, $i );

            if ( !empty( $values ) ) {
              $response = $this->send_to_salesforce( $relationship->to_object, $values,
              json_decode( $relationship->unique_sf_fields ), $response_ids, $i );

              if ( !$response["success"] ) {
                $is_success = false;
                array_push( $error_message, $response["error_message"] );
                break; // no need to continue
              }
            }
            $i++;
          }
        }
      } // for each relationship

      // handle order sync response
      $this->handle_order_sync_response( $order_id, $is_success, $error_message );
    }

    /**
     * Extract and return order items from order object
     * @param NWSI_Order_Model  $order
     * @return array - array of NWSI_Product_Model
     */
    private function get_products_from_order( $order ) {
      $product_items = $order->get_items();

      // prepare order items/products
      $products = array();
      foreach( $product_items as $product_item ) {
        // process order product
        $product = new NWSI_Product_Model( $product_item["product_id"] );
        $product->set_order_product_meta_data( $product_item["item_meta"] );

        array_push( $products, $product );
      }

      return $products;
    }

    /**
     * Save sync status and error messages to order meta data
     * @param int     $order_id
     * @param boolean $is_successful
     * @param array   $error_message
     */
    private function handle_order_sync_response( $order_id, $is_successful, $error_message ) {
      if ( $is_successful ) {
        update_post_meta( $order_id, "_sf_sync_status", "success" );
      } else {
        update_post_meta( $order_id, "_sf_sync_status", "failed" );
        update_post_meta( $order_id, "_sf_sync_error_message", json_encode( $error_message ) );
      }
    }

    /**
    * Send values to given object via Salesforce API
    * @param string  $to_object
    * @param array   $values
    * @param array   $unique_sf_fields
    * @param array   $response_ids (reference)
    * @param int     $id_index - in case we've multiple sf objects of the same type
    * @return array - [success, error_message]
    */
    private function send_to_salesforce( $to_object, $values, $unique_sf_fields, &$response_ids, $id_index = null ) {
      $response = array();

      $sf_response = $this->sf->create_object( $to_object, $values, $unique_sf_fields );

      // obtain SF response ID if any
      if ( $sf_response["success"] ) {
        $response["success"] = true;
        if ( is_null( $id_index ) ) {
          $response_ids[ $to_object ] = $sf_response["id"];
          // echo $to_object . ": " . $response_ids[ $to_object ] . "\n";
        } else {
          $response_ids[ $to_object ][ $id_index ] = $sf_response["id"];
          // echo $to_object . ", " . $id_index . ": " . $response_ids[ $to_object ][ $id_index ] . "\n";
        }
      } else {
        $response["success"] = false;
        $response["error_message"] = $sf_response["error_code"] . " (" . $to_object . "): " . $sf_response["error_message"];
      }

      return $response;
    }

    /**
     * Check and return true if date is in Y-m-d format
     * @param string $date
     * @return boolean
     */
    private function is_correct_date_format( $date ) {
      if ( preg_match( "/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $date ) ) {
        return true;
      } else {
        return false;
      }
    }

    /**
    * Return populated array with field names and values
    * @param array      $connections
    * @param NWSI_Model $item
    * @return array
    */
    private function get_values( $connections, $item ) {
      $values = array();
      error_log( '--------------------------------' );
      foreach( $connections as $connection ) {
        error_log( print_r( $connection, true ) );
        if ( $connection->source == "woocommerce" ) {
          $value = $item->get( $connection->from );
          // validation
          if ( $connection->type == "boolean" && !is_bool( $value ) ) {
            $value = null;
          } else if ( in_array( $connection->type, array( "double", "currency", "number", "percent" ) )
            && !is_numeric( $value ) ) {
            $value = null;
          } else if ( $connection->type == "email" && !filter_var( $value, FILTER_VALIDATE_EMAIL) === false ) {
            $value = null;
          } else if ( $connection->type == "date" ) {
            if ( !$this->is_correct_date_format( $value ) ) {
              try {
                $value = explode( " ", $value )[0];
                if ( !$this->is_correct_date_format( $value ) ) {
                  $value = date( "Y-m-d" );
                }
              } catch( Exception $exc ) {
                // not user friendly fallback but it will solved any required dates
                $value = date( "Y-m-d" );
              }
            }
          } else if ( !is_string( $value ) ) {
            $value = null;
          }

        } else if ( $connection->source == "sf-picklist" || $connection->source == "custom" ) {
          if ( $connection->type == "date" && $connection->value == "current" ) {
            $value = date( "Y-m-d" );
          } else {
            $value = $connection->value;
          }
        }

        if ( !empty( $value ) ) {
          if ( array_key_exists( $connection->to, $values ) ) {
            $values[ $connection->to ] .= ", " . $value;
          } else {
            $values[ $connection->to ] = $value;
          }
        }
      }
      return $values;
    }

    /**
    * Check dependecies and update values array if needed
    * @param string 	$to_object
    * @param array		$response_ids
    * @param array		$values (reference)
    * @param array    $required_sf_objects
    * @param string   $from_object
    * @param int      $id_index - in case we've multiple sf objects of the same type
    */
    private function set_dependencies( $to_object, &$values, $required_sf_objects, $response_ids, $from_object, $id_index = null ) {
      foreach( $required_sf_objects as $required_sf_object ) {
        if ( is_array( $response_ids[ $required_sf_object->name ] ) ) {
          if ( empty( $id_index ) ) {
            $values[ $required_sf_object->id ] = $response_ids[ $required_sf_object->name ][0];
          } else {
            $values[ $required_sf_object->id ] = $response_ids[ $required_sf_object->name ][ $id_index ];
          }
        } else {
          $values[ $required_sf_object->id ] = $response_ids[ $required_sf_object->name ];
        }
      }
    }

    /**
    * Sort relationships by Salesforce object dependencies
    * @param array $relationships
    * @return array
    */
    private function prioritize_relationships( $relationships ) {
      $prioritized_relationships = $relationships;

      for ( $i = 0; $i < sizeof( $relationships ); $i++ ) {
        $required_objects = json_decode( $relationships[$i]->required_sf_objects );
        foreach( $required_objects as $required_object ) {
          for ( $j = 0; $j < sizeof( $relationships ); $j++ ) {
            if ( $i == $j ) {
              continue;
            }
            if ( $required_object->name == $relationships[$j]->to_object ) {
              $new_position = $this->get_relationship_index_in_array( $prioritized_relationships, $required_object->name );
              if ( $new_position != -1 ) { // required object exists in array
                $current_position = $this->get_relationship_index_in_array( $prioritized_relationships, $relationships[$i]->to_object );
                if ( $new_position > $current_position ) { // object that depends is located before in array

                  $temp = array_splice( $prioritized_relationships, $current_position, 1 );
                  array_splice( $prioritized_relationships, $new_position, 0, $temp );
                }
              }
            }
          }
        } // foreach
      }
      return $prioritized_relationships;
    }

    /**
     * Return position of object in relationships array with the same to_object
     * value or -1 in case of no matching object
     * @param array   $relationships
     * @param string  $to_object
     * @return int
     */
    private function get_relationship_index_in_array( $relationships, $to_object ) {
      for( $i = 0; $i < sizeof( $relationships ); $i++ ) {
        if ( $relationships[$i]->to_object == $to_object ) {
          return $i;
        }
      }
      return -1;
    }

  }
}
