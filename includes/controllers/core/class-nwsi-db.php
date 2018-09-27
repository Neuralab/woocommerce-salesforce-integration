<?php
if ( !defined( "ABSPATH" ) ) {
  exit;
}


if ( !class_exists( "NWSI_DB" ) ) {
  class NWSI_DB {

    /**
     * Name of the relationship table.
     * @var string
     */
    private $rel_table_name;

    /**
     * Class constructor.
     */
    public function __construct() {
      global $wpdb;

      $this->rel_table_name = $wpdb->prefix . "nwsi_relationships";
    }

    /**
    * Create relationship table
    */
    public function create_relationship_table() {
      global $wpdb;

      $charset_collate = $wpdb->get_charset_collate();
      $table_name = esc_sql( $this->rel_table_name );
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
     * Delete relationship table.
     */
    public function delete_relationship_table() {
      global $wpdb;

      $wpdb->query( "DROP TABLE IF EXISTS " . esc_sql( $this->rel_table_name ) );
    }

    /**
    * Check if the relationship table is empty.
    *
    * @return boolean
    */
    public function is_relationship_table_empty() {
      global $wpdb;

      $query = "SELECT * FROM $this->rel_table_name LIMIT 1";

      $results = $wpdb->get_results( $query );
      if ( empty( $results ) ) {
        return true;
      }
      return false;
    }

    /**
    * Save new relationship to the database and return true if successful or
    * false otherwise.
    *
    * @param string $from                 Name of WooCommerce object.
    * @param string $from_label           Label of WooCommerce object.
    * @param string $to                   Name of Salesforce object.
    * @param string $to_label             Label of Salesforce object.
    * @param mixed  $data                 Relationships array or relationships JSON string.
    * @param string $required_sf_objects  Defaults to empty string.
    * @param string $unique_sf_fields     Defaults to empty string.
    * @return boolean
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
      $wpdb->insert( $this->rel_table_name, array(
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
    * Update relationship and return true if successful or false otherwise.
    *
    * @param string  $key
    * @param array   $data
    * @return boolean
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
      $wpdb->update(
        $this->rel_table_name,
        $update_data,
        array( "hash_key" => $key )
      );
      return true;
    }

    /**
    * Extract unique salesforce fields from the provided array and return them
    * as array or string if $to_json is set to true.
    *
    * @param array   $data
    * @param string  $to_json Defaults to true.
    * @return array|string
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
    * Extract required salesforce objects from provided array and return them in
    * form of array or string if $to_json is set to true.
    *
    * @param array   $data
    * @param string  $to_json Defaults to true.
    * @return array|string
    */
    private function get_required_sf_objects( $data, $to_json = true ) {

      $required_sf_objects = array();
      $i = 0;

      while( array_key_exists( "requiredSfObject-" . $i, $data ) ) {
        if ( array_key_exists( "requiredSfObjectIsActive-" . $i, $data ) ) {
          $parts = explode( "|", $data["requiredSfObject-" . $i] );
          if ( count( $parts ) < 3 ) {
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
    * Transform relationship array to json string. Returns empty string if no
    * relationships provided.
    *
    * @param array $data
    * @return string
    */
    private function relationship_data_to_json( $data ) {

      $relationships = array();
      for( $i = 0; $i < intval( $data["fields-count"] ); $i++ ) {
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
        . "required_sf_objects, unique_sf_fields FROM $this->rel_table_name WHERE hash_key=%s";

      $response = $wpdb->get_results( $wpdb->prepare( $query, $key ) );
      if ( empty( $response ) ) {
        return null;
      } else {
        return $response[0];
      }
    }

    /**
    * Return all active relationships.
    *
    * @return array
    */
    public function get_active_relationships() {
      global $wpdb;

      $query  = "SELECT from_object, to_object, relationships, active, required_sf_objects, unique_sf_fields ";
      $query .= "FROM $this->rel_table_name WHERE active=1";

      return $wpdb->get_results( $query );
    }

    /**
    * Return all relationships.
    *
    * @return array
    */
    public function get_relationships() {
      global $wpdb;

      $query  = "SELECT id, date_created, date_updated, from_object, ";
      $query .= "from_object_label, to_object, to_object_label, hash_key, active ";
      $query .= "FROM $this->rel_table_name";

      return $wpdb->get_results( $query );
    }

    /**
    * Delete relationships and return true if successful or false otherwise.
    *
    * @param array $ids
    * @return boolean
    */
    public function delete_relationships_by_id( $ids ) {
      global $wpdb;

      $sql_ids = $this->sanitize_ids( $ids );
      $query = "DELETE FROM $this->rel_table_name WHERE id IN (" . implode( ",", $sql_ids ) . ")";

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
    * Set active attribute to given value.
    *
    * @param int   $value
    * @param array $ids
    * @return boolean
    */
    private function set_active_attribute( $value, $ids ) {
      global $wpdb;

      $sql_ids = $this->sanitize_ids( $ids );
      $query = "UPDATE $this->rel_table_name SET active=" . $value . " WHERE id IN (" . implode( ",", $sql_ids ) . ")";

      return $wpdb->query( $query );
    }

    /**
    * Return array of sanitized ids.
    *
    * @param array $ids
    * @return array
    */
    private function sanitize_ids( $ids ) {
      $sql_ids = array();
      for ( $i = 0; $i < count( $ids ); $i++ ) {
        array_push( $sql_ids, intval( $ids[$i] ) );
      }

      return $sql_ids;
    }

  }
}
