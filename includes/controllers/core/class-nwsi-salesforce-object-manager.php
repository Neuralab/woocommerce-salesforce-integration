<?php
if ( !defined( "ABSPATH" ) ) {
  exit;
}

if ( !class_exists( "NWSI_Salesforce_Token_Manager" ) ) {
  require_once( "class-nwsi-salesforce-token-manager.php" );
}

if ( !class_exists( "NWSI_Salesforce_Object_Manager" ) ) {
  /**
   * Core class responsible for managing SalesForce objects.
   */
  class NWSI_Salesforce_Object_Manager extends NWSI_Salesforce_Token_Manager {

    /**
     * Salesforce API version used in the plugin.
     *
     * @var string
     */
    private $api_version = "v37.0";

    /**
     * Creates object if it doesn't already exists
     *
     * @param  string  $name                Name of the SF object.
     * @param  array   $values
     * @param  array   $unique_sf_fields    Defaults to empty array.
     * @param  boolean $no_error_handling   Defaults to false.
     * @return array
     */
    public function create_object( string $name, array $values, array $unique_sf_fields = array(), bool $no_error_handling = false ) {
      // check if object with given unique_sf_field values exists
      if ( !empty( $unique_sf_fields ) ) {
        $get_values = array();
        foreach( $unique_sf_fields as $unique_sf_field ) {
          if ( array_key_exists( $unique_sf_field, $values ) && !empty( $values[ $unique_sf_field ] ) ) {
            $get_values[ $unique_sf_field ] = $values[ $unique_sf_field ];
          }
        }

        if ( !empty( $get_values ) ) {
          $get_object_response = $this->get_object( $name, $get_values );
          // if object with the same values of unique field/s exists, return his ID
          if ( $get_object_response["success"] ) {
            // if an order exists, delete order's items so they can be added after
            if ( $name === "Order" ) {
              $this->delete_orders_items( $get_object_response["id"] );
            }
            return $get_object_response;
          }
        }
      }

      // post new object
      $url = $this->instance_url . "/services/data/" . $this->api_version . "/sobjects/" . $name;
      $sf_response = $this->get_response( $url, true, "post", json_encode( $values ), "application/json" );

      if ( !$no_error_handling ) {
        $error_response = $this->get_error_status( $sf_response );

        if ( $error_response == "solved" ) {
          $sf_response = $this->get_response( $url, true, "post", json_encode( $values ), "application/json" );
        } else if ( $error_response == "duplicate value" ) {
          $object_val = $this->get_object( $name, $values );
          return $object_val;
        } else if ( $error_response == "no standard price" ) {
          $standard_price_book_id = $this->get_standard_price_book_id();

          $values["Pricebook2Id"] = $standard_price_book_id;
          return $this->create_object( $name, $values, $unique_sf_fields, true );
        } else if ( $error_response == "failed" ) {
          // do nothing, for now...
        }
      }

      if ( isset( $sf_response["success"] ) && $sf_response["success"] ) {
        $response["success"] = true;
        $response["id"]      = $sf_response["id"];
      } else if ( isset( $sf_response["done"] ) && $sf_response["done"] && count( $sf_response["records"] ) > 0 ) {
        $response["success"] = true;
        $response["id"]      = $sf_response["records"][0]["Id"];
      } else {
        $response = $this->set_response_error_message( $sf_response );
      }

      return $response;
    }

    /**
     * Return object's ID that has provided values.
     *
     * @param  string  $name
     * @param  array   $values
     * @return array
     */
    private function get_object( string $name, array $values ) {
      $query = "SELECT Id FROM " . $name . " WHERE ";

      $where_query_part = "";
      foreach ( $values as $key => $val ) {
        // checking for boolean attributes is not needed!
        if ( is_bool( $val ) || $val == "true" || $val == "false" ) {
          continue;
        }

        if ( !empty( $where_query_part ) ) {
          $where_query_part .= " AND ";
        }

        if ( is_numeric( $val ) ) {
          $where_query_part .= $key . "=" . $val;
        } else {
          $where_query_part .= $key . "='" . $val . "'";
        }
      }
      $query .= $where_query_part;

      $url = $this->instance_url . "/services/data/" . $this->api_version . "/query?q=" . urlencode( $query );
      $sf_response = $this->get_response( $url );
      $response = array();

      if ( isset( $sf_response["done"] ) && $sf_response["done"] && count( $sf_response["records"] ) > 0 ) {
        $response["success"] = true;
        $response["id"]      = $sf_response["records"][0]["Id"];
      } else {
        $response = $this->set_response_error_message( $sf_response );
      }
      return $response;
    }

    /**
     * Extract error message and code from salesforce response to appropriate array
     * @param array $sf_response
     * @return array
     */
    private function set_response_error_message( array $sf_response ) {
      $response["success"] = false;
      if ( !empty( $sf_response[0]["errorCode"] ) && !empty( $sf_response[0]["message"]) ) {
        $response["error_code"]    = $sf_response[0]["errorCode"];
        $response["error_message"] = $sf_response[0]["message"];
      } else {
        $response["error_code"]    = "UNKNOWN";
        $response["error_message"] = "Unknown error occurred.";
      }
      return $response;
    }

    /**
     * Return Standard Price Book's ID
     * @return string
     */
    public function get_standard_price_book_id() {
      $query = "SELECT Id FROM Pricebook2 WHERE IsStandard=true";

      $url = $this->instance_url . "/services/data/" . $this->api_version . "/query?q=" . urlencode( $query );
      $response = $this->get_response( $url );
      $error_response = $this->get_error_status( $response );
      if ( $error_response == "solved" ) {
        $response = $this->get_response( $url );
      } else if ( $error_response == "failed" ) {
        return null;
      }

      if ( $response["done"] ) {
        return $response["records"][0]["Id"];
      } else {
        return null;
      }
    }

    /**
     * Return array of all available Salesforce objects or null in case of failure
     * @return array
     */
    public function get_all_objects() {
      $url = $this->instance_url . "/services/data/" . $this->api_version . "/sobjects/";
      $response = $this->get_response( $url );

      $error_response = $this->get_error_status( $response );
      if ( $error_response == "solved" ) {
        $response = $this->get_response( $url );
      } else if ( $error_response == "failed" ) {
        return null;
      }

      return $response;
    }

    /**
     * Return object description or null in case of failure
     * @param string $object_name
     * @return array|null
     */
    public function get_object_description( $object_name ) {
      $url = $this->instance_url . "/services/data/" . $this->api_version . "/sobjects/" . $object_name . "/describe/" ;
      $response = $this->get_response( $url );

      $error_response = $this->get_error_status( $response );
      if ( $error_response == "solved" ) {
        $response = $this->get_response( $url );
      } else if ( $error_response == "failed" ) {
        return null;
      }

      return $response;
    }

    /**
     * Call API and returns array of products with unit prices
     * @return array
     */
    private function query_products() {
      $query = "SELECT Product2.Id, Product2.Name, Product2.ProductCode, Product2.Description, "
      . "Product2.isActiveOnlineProduct__c, PricebookEntry.UnitPrice FROM PricebookEntry";

      $url = $this->instance_url . "/services/data/" . $this->api_version . "/query?q=" . urlencode( $query );

      return $this->get_response( $url );
    }

    /**
     * Return array of products with unit prices
     * @return array or null if failed
     */
    public function get_products() {
      $response = $this->query_products();

      $error_response = $this->get_error_status( $response );
      if ( $error_response == "solved" ) {
        $response = $this->query_products();
      } else if ( $error_response == "failed" ) {
        return null;
      }

      try {
        $products = array();
        foreach( $response["records"] as $product ) {
          if ( !$this->is_product_id_in_array( $product["Product2"]["Id"], $products ) ) {
            array_push( $products, array(
              "id"          => trim( $product["Product2"]["Id"] ),
              "name"        => $product["Product2"]["Name"],
              "code"        => $product["Product2"]["ProductCode"],
              "unit_price"  => $product["UnitPrice"],
              "is_active"   => $product["Product2"]["isActiveOnlineProduct__c"],
              "wand_id"     => $product["Product2"]["Product_4D_Wand_ID__c"]
            ) );
          }
        }
        return $products;
      } catch( Exception $e ) {
        return null;
      }
    }

    /**
     * Return true if there is a provided id multidimensional products array
     * @param string 	$product_id
     * @param array 	$products (2D)
     * @return boolean
     */
    private function is_product_id_in_array( $product_id, $products ) {
      foreach( $products as $product ) {
        if ( $product["id"] == $product_id ) {
          return true;
        }
      }
      return false;
    }

    /**
     * Scan API response array for errors and call appropriate handle method if any
     * @param array $response
     * @return string "solved", "failed", "none", "duplicate value"
     */
    private function get_error_status( $response ) {

      if ( empty( $response ) ) {
        return "failed";
      }

      if ( array_key_exists( 0, $response ) && array_key_exists( "errorCode", $response[0] ) ) {
        if ( $response[0]["errorCode"] == "INVALID_SESSION_ID" ) {
          if ( $this->revalidate_token() ) {
            return "solved";
          } else {
            return "failed";
          }
        } else if ( $response[0]["errorCode"] == "DUPLICATE_VALUE" ||
            $response[0]["errorCode"] == "FIELD_INTEGRITY_EXCEPTION" )  {
          return "duplicate value";
        } else if ( $response[0]["errorCode"] == "STANDARD_PRICE_NOT_DEFINED" ) {
          return "no standard price";
        }

        return "failed";
      }

      return "none";
    }

    /**
     * Delete all OrderItems connected to an Order with given ID
     * @param string $order_id
     */
    private function delete_orders_items( $order_id ) {
      $query = "SELECT Id FROM OrderItem WHERE OrderId='" . $order_id . "'";

      $url = $this->instance_url . "/services/data/" . $this->api_version . "/query?q=" . urlencode( $query );
      $order_items_response = $this->get_response( $url );
      foreach( $order_items_response["records"] as $order_item ) {
        $url_delete = $this->instance_url . "/services/data/" . $this->api_version . "/sobjects/OrderItem/" . urlencode( $order_item["Id"] );
        $this->get_response( $url_delete, true, "delete" );
      }

     }
  }
}
