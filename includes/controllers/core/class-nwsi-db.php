<?php
if ( !defined( "ABSPATH" ) ) {
  exit;
}


if ( !class_exists( "NWSI_DB" ) ) {
  class NWSI_DB {

    /**
    * Create relationship table
    */
    public function create_relationship_table() {

      global $wpdb;

      $charset_collate = $wpdb->get_charset_collate();
      $table_name = $wpdb->prefix . "nwsi_relationships";

      $query = "CREATE TABLE $table_name (
        id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
        hash_key VARCHAR(128) NOT NULL,
        relationships TEXT NOT NULL,
        from_object VARCHAR(255) NOT NULL,
        from_object_label VARCHAR(255),
        to_object VARCHAR(255) NOT NULL,
        to_object_label VARCHAR(255),
        required_sf_objects TEXT,
        unique_sf_fields TEXT,
        date_updated TIMESTAMP,
        date_created TIMESTAMP,
        active TINYINT DEFAULT 0,
        UNIQUE KEY id (id),
        UNIQUE (hash_key)
      ) $charset_collate;";

      require_once( ABSPATH . "wp-admin/includes/upgrade.php" );
      dbDelta( $query );
    }

    /**
     * Delete relationship table
     */
    public function delete_relationship_table() {

      global $wpdb;

      $table_name = $wpdb->prefix . "nwsi_relationships";

      $wpdb->query( "DROP TABLE IF EXISTS " . $table_name );
    }

    /**
    * Check if relationship table is empty
    */
    public function is_relationship_table_empty() {

      global $wpdb;

      $table_name = $wpdb->prefix . "nwsi_relationships";

      $query = "SELECT * FROM $table_name LIMIT 1";

      $results = $wpdb->get_results( $query );
      if ( empty( $results ) ) {
        return true;
      }

      return false;
    }

    /**
    * Save new relationship to database
    * @param string $from - name of WooCommerce object
    * @param string $from_label - label of WooCommerce object
    * @param string $to - name of Salesforce object
    * @param string $to - label of Salesforce object
    * @param mixed  $data - relationships ($_POST) array or relationships JSON string
    * @param string $required_sf_objects - default = ""
    * @param string $unique_sf_fields - default = ""
    * @return boolean - false if something went wrong
    */
    public function save_new_relationship( $from, $from_label, $to, $to_label, $data, $required_sf_objects = "", $unique_sf_fields = "" ) {

      if ( empty( $from ) || empty( $to ) || empty( $data ) ) {
        return false;
      }

      if ( is_array( $data ) ) {
        $relationships = $this->relationship_data_to_json( $data );
      } else {
        $relationships = $data;
      }

      if ( empty( $required_sf_objects ) ) {
        $required_sf_objects = $this->get_required_sf_objects( $data );
      }

      if ( empty( $unique_sf_fields ) ) {
        $unique_sf_fields = $this->get_unique_sf_fields( $data );
      }

      $key = md5( date('Y-m-d H:i:s') . $from . $to );

      global $wpdb;

      $wpdb->insert( $wpdb->prefix . "nwsi_relationships", array(
        "from_object"         => $from,
        "from_object_label"   => $from_label,
        "to_object"           => $to,
        "to_object_label"     => $to_label,
        "relationships"       => $relationships,
        "hash_key"            => $key,
        "required_sf_objects" => $required_sf_objects,
        "unique_sf_fields"    => $unique_sf_fields,
        "date_created"        => date('Y-m-d H:i:s'),
        "date_updated"        => date('Y-m-d H:i:s')
        ) );

      return true;
    }

    /**
    * Update relationship
    * @param string  $key
    * @param array   $data
    * @return boolean - false if something went wrong
    */
    public function update_relationship( $key, $data ) {
      $relationships = $this->relationship_data_to_json( $data );

      $required_sf_objects = $this->get_required_sf_objects( $data );
      $unique_sf_fields = $this->get_unique_sf_fields( $data );

      if ( empty( $key ) && ( empty( $relationships ) || empty( $required_sf_objects ) || empty( $unique_sf_fields ) ) ) {
        return false;
      }

      $update_data = array(
        "date_updated"        => date('Y-m-d H:i:s'),
        "required_sf_objects" => $required_sf_objects,
        "unique_sf_fields"    => $unique_sf_fields,
      );

      if ( !empty( $relationships ) ) {
        $update_data["relationships"] = $relationships;
      }

      global $wpdb;

      $wpdb->update( $wpdb->prefix . "nwsi_relationships",
      $update_data,
      array( "hash_key" => $key ) );
      return true;
    }

    /**
    * Extract unique sf fields from provided array ($_POST)
    * @param array   $data
    * @param string  $to_json - default = true
    * @return mixed - array or string if $to_json=true
    */
    private function get_unique_sf_fields( $data, $to_json = true ) {

      $unique_sf_fields = array();
      $i = 0;

      while( array_key_exists( "uniqueSfField-" . $i, $data ) ) {
        if ( $data["uniqueSfField-" . $i] != "none" ) {
          array_push( $unique_sf_fields, $data["uniqueSfField-" . $i] );
        }
        $i++;
      }
      if ( $to_json ) {
        return json_encode( $unique_sf_fields );
      }
      return $unique_sf_fields;
    }

    /**
    * Extract required sf objects from provided array ($_POST)
    * @param array   $data
    * @param string  $to_json - default = true
    * @return mixed - array or string if $to_json=true
    */
    private function get_required_sf_objects( $data, $to_json = true ) {

      $required_sf_objects = array();
      $i = 0;

      while( array_key_exists( "requiredSfObject-" . $i, $data ) ) {
        if ( array_key_exists( "requiredSfObjectIsActive-" . $i, $data ) ) {
          $parts = explode( "|", $data["requiredSfObject-" . $i] );
          if ( sizeof( $parts ) < 3 ) {
            continue;
          }
          $required_object = array(
            "name"  => $parts[0],
            "label" => $parts[1],
            "id"    => $parts[2]
          );
          array_push( $required_sf_objects, $required_object );
        }
        $i++;
      }
      if ( $to_json ) {
        return json_encode( $required_sf_objects );
      }
      return $required_sf_objects;
    }

    /**
    * Transform relationship array to json
    * @param array $data
    * @return string - empty if no relationships
    */
    private function relationship_data_to_json( $data ) {

      $relationships = array();
      for( $i = 0; $i < intval( $data["numOfFields"] ); $i++ ) {
        if ( !empty( $data[ "wcField-" . $i ] ) && $data[ "wcField-" . $i ] != "none" ) {
          $temp = array(
            "source"  => $data[ "wcField-" . $i . "-source" ],
            "type"    => $data[ "wcField-" . $i . "-type" ],
            "from"    => $data[ "wcField-" . $i ],
            "to"      => $data[ "sfField-" . $i ]
          );

          if ( strpos( $data[ "wcField-" . $i ], "custom" ) !== false ) {
            $temp["from"] = "custom";
            if ( $temp["type"] == "boolean" ) {
              $value = explode( "-", $data[ "wcField-" . $i ] )[1];
              if ( !is_null( $value ) ) {
                $temp["value"] = $value;
              }
            } else if ( $temp["type"] == "date" ) {
              $temp["value"] = "current";
            } else {
              $temp["value"] = $data[ "wcField-" . $i . "-custom" ];
            }
          } else if ( strpos( $data[ "wcField-" . $i . "-source" ], "sf-picklist" ) !== false ) {
            $temp["value"] = $data[ "wcField-" . $i ];
            $temp["from"]  = "salesforce";
          }

          array_push( $relationships, $temp );
        }
      }

      if ( empty( $relationships ) ) {
        return "";
      }

      return json_encode( $relationships );
    }

    /**
    * Return relationship with provided hash key
    * @param string $key
    * @return array
    */
    public function get_relationship_by_key( $key ) {
      global $wpdb;

      $query = "SELECT relationships, from_object, from_object_label, to_object, to_object_label, "
      . "required_sf_objects, unique_sf_fields FROM " . $wpdb->prefix
      . "nwsi_relationships WHERE hash_key=%s";
      $response = $wpdb->get_results( $wpdb->prepare( $query, $key ) );

      if ( empty( $response ) ) {
        return null;
      } else {
        return $response[0];
      }
    }

    /**
    * Return all active relationships
    * @return array
    */
    public function get_active_relationships() {

      global $wpdb;

      $query = "SELECT from_object, to_object, relationships, active, required_sf_objects, unique_sf_fields ";
      $query .= "FROM " . $wpdb->prefix . "nwsi_relationships WHERE active=1";

      return $wpdb->get_results( $query );
    }

    /**
    * Return all relationships
    * @return array
    */
    public function get_relationships() {

      global $wpdb;

      $query = "SELECT id, date_created, date_updated, from_object, ";
      $query .= "from_object_label, to_object, to_object_label, hash_key, active ";
      $query .= "FROM " . $wpdb->prefix . "nwsi_relationships";

      return $wpdb->get_results( $query );
    }

    /**
    * Delete relationships
    * @param $ids - array of relationships ids
    * @return boolean
    */
    public function delete_relationships_by_id( $ids ) {

      global $wpdb;

      $sql_ids = $this->sanitize_ids( $ids );
      $query = "DELETE FROM " . $wpdb->prefix . "nwsi_relationships WHERE id IN (" . implode( ",", $sql_ids ) . ")";

      return $wpdb->query( $query );
    }

    /**
    * Activate relationships
    * @param $ids - array of relationships ids
    * @return boolean
    */
    public function activate_relationships_by_id( $ids ) {
      return $this->set_active_attribute( 1, $ids );
    }

    /**
    * Deactivate relationships
    * @param array $ids - relationships ids
    * @return boolean
    */
    public function deactivate_relationships_by_id( $ids ) {
      return $this->set_active_attribute( 0, $ids );
    }

    /**
    * Set active attribute to given value
    * @param int   $value
    * @param array $ids
    * @return boolean
    */
    private function set_active_attribute( $value, $ids ) {
      global $wpdb;

      $sql_ids = $this->sanitize_ids( $ids );
      $query = "UPDATE " . $wpdb->prefix . "nwsi_relationships SET active=" . $value . " WHERE id IN (" . implode( ",", $sql_ids ) . ")";

      return $wpdb->query( $query );
    }

    /**
    * Return array of sanitized ids
    * @param array $ids
    * @return array
    */
    private function sanitize_ids( $ids ) {
      $sql_ids = array();
      for ( $i = 0; $i < sizeof( $ids ); $i++ ) {
        array_push( $sql_ids, intval( $ids[$i] ) );
      }

      return $sql_ids;
    }

    /**
    * Return WC_Order magic properties from orders in DB
    * @return array
    */
    public function get_order_meta_keys() {

      global $wpdb;

      $query = "SELECT DISTINCT( meta_key ) FROM " . $wpdb->prefix . "postmeta WHERE post_id=(";
      $query .= "SELECT ID FROM " . $wpdb->prefix . "posts WHERE post_type='shop_order' ";
      $query .= "ORDER BY ID DESC )";

      return $wpdb->get_results( $query );
    }

    /**
    * Return WC_Product magic properties from products in DB
    * @return array
    */
    public function get_product_meta_keys() {

      global $wpdb;

      $query = "SELECT DISTINCT( meta_key ) FROM " . $wpdb->prefix . "postmeta WHERE post_id IN (";
      $query .= "SELECT ID FROM " . $wpdb->prefix . "posts WHERE post_type='product' OR post_type='product_variation')";

      return $wpdb->get_results( $query );
    }

    /**
    * Return properties from order products in DB
    * @return array
    */
    public function get_order_product_meta_keys() {

      global $wpdb;

      $query = "SELECT DISTINCT ( meta_key ) FROM " . $wpdb->prefix . "woocommerce_order_itemmeta";

      return $wpdb->get_results( $query );
    }
  }
}
