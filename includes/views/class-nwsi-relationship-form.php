<?php
if ( !defined( "ABSPATH" ) ) {
  exit;
}

if ( !class_exists( "NWSI_Relationships_Form" ) ) {
  /**
   * View class for managing single relationship form.
   */
  class NWSI_Relationships_Form {

    /**
     * @var NWSI_Salesforce_Object_Manager
     */
    private $sf;

    /**
     * @var NWSI_HTML_Builder
     */
    private $html;

    /**
     * List of connections between Salesforce and WooCommerce fields.
     * @var array
     */
    private $relationships = array();

    /**
     * List of all fields for choosen Salesforce object.
     * @var array
     */
    private $sf_fields = array();

    /**
     * List of required Salesforce fields for current relationships.
     * @var array
     */
    private $required_sf_fields = array();

    /**
     * List of unique Salesforce fields.
     * @var array
     */
    private $unique_sf_fields = array();

    /**
     * Initialize class attributes.
     * @param NWSI_Salesforce_Object_Manager $sf
     */
    public function __construct( NWSI_Salesforce_Object_Manager $sf ) {
      require_once NWSI_DIR_PATH . "includes/models/class-nwsi-order-model.php";
      require_once NWSI_DIR_PATH . "includes/models/class-nwsi-order-item-model.php";
      require_once NWSI_DIR_PATH . "includes/models/class-nwsi-product-model.php";

      require_once NWSI_DIR_PATH . "includes/controllers/utilites/class-nwsi-html-builder.php";

      $this->sf   = $sf;
      $this->html = new NWSI_HTML_Builder();

      add_action( "wp_ajax_nwsi_get_picklist", array( $this, "get_picklist_via_ajax" ) );
    }

    /**
     * Echo form for editing the existing relationship provided as a parameter.
     * Existing relationship should contain properties:
     *  relationships       => array
     *  from_object         => string
     *  from_object_label   => string
     *  to_object           => string
     *  to_object_label     => string
     *  required_sf_objects => array
     *  unique_sf_fields    => array
     *
     * @param stdClass $rel Relationship object.
     */
    public function display_existing( stdClass $rel ) {
      $this->relationships      = json_decode( $rel->relationships );
      $this->required_sf_fields = json_decode( $rel->required_sf_objects );
      $this->unique_sf_fields   = json_decode( $rel->unique_sf_fields );

      $this->display_blank( $rel->from_object, $rel->from_object_label,
        $rel->to_object, $rel->to_object_label );
    }

    /**
     * Echo HTML form for creating new relationships.
     *
     * @param string  $from       WooCommerce object name.
     * @param string  $from_label WooCommerce object label.
     * @param string  $to         Salesforce object name.
     * @param string  $to_label   Salesforce object label.
     */
    public function display_blank( $from, $from_label, $to, $to_label ) {
      $sf_object_description = $this->sf->get_object_description( $to );
      $sf_fields = array_filter( $sf_object_description["fields"], array( $this, "is_safe_for_user_pick" ) );
      $sf_fields = $this->append_additional_info_to_label( array_values( $sf_fields ) );

      $sf_keys = array();
      for ( $i = 0; $i < count( $sf_fields ); $i++ ) {
        $sf_fields[ $i ]["data"] = array( "type" => $sf_fields[ $i ]["type"] );
        array_push( $sf_keys, $sf_fields[ $i ]["name"] );
      }
      $this->sf_fields = array_combine( $sf_keys, $sf_fields );

      $this->display_title( empty( $this->relationships ) );

      $this->display_main_section( $from, $from_label, $to_label );

      $this->display_unique_section();
      $this->display_required_objects_section( $to, $to_label );
    }


    public function get_picklist_via_ajax() {
      wp_send_json( $_REQUEST );
    }

    /**
     * Echo form title.
     *
     * @param boolean $is_new
     */
    private function display_title( $is_new ) {
      ?>
      <h3>
        <?php
        if ( $is_new ) {
          _e( "New relationship", "woocommerce-integration-nwsi" );
        } else {
          _e( "Edit relationship", "woocommerce-integration-nwsi" );
        }
        ?>
      </h3>
      <p>
        <?php _e( "Salesforce fields with * are required.", "woocommerce-integration-nwsi" ); ?>
        <?php _e( "If more than one value is assigned to Salesforce field, they will be concatenated.", "woocommerce-integration-nwsi" ); ?>
      </p>
      <?php
    }

    /**
     * Echo main form inputs for editing existing or creating a new relationship.
     *
     * @param string $from
     * @param string $from_label
     * @param string $to_label
     */
    private function display_main_section( $from, $from_label, $to_label ) {
      ?>
      <table class="form-table" id="nwsi-new-relationship-form" >
        <thead>
          <th id="nwsi-from-object"><?php echo $to_label; ?> (Salesforce)</th>
          <th id="nwsi-to-object"><?php echo $from_label; ?> (WooCommerce)</th>
        </thead>
        <tbody>
          <?php $i = 0; ?>
          <?php foreach ( $this->relationships as $relationship ): ?>
            <?php $sf_field = $this->sf_fields[ $relationship->to ]; ?>
            <?php $required = $this->is_required( $sf_field ); ?>
            <tr valign="top">
              <td scope="row" class="titledesc">
                <?php
                echo $this->html->build_select( $this->sf_fields,
                  "sfField-" . $i, "nwsi-sf-field", $relationship->to, false, false );
                ?>
              </td>
              <td class="forminp">
                <?php
                if ( $relationship->from === "salesforce" ) {
                  $selected = $relationship->value;
                } else if ( $relationship->from === "custom" ) {
                  if ( $relationship->type === "date" ) {
                    $selected = "custom-current-date";
                  } else if ( $relationship->type === "boolean" ) {
                    $selected = "custom-" . $relationship->value;
                  } else {
                    $selected = "custom-value";
                  }
                } else {
                  $selected = $relationship->from;
                }

                $type   = property_exists( $relationship, "type" ) ? $relationship->type : "";
                $value  = property_exists( $relationship, "value" ) ? $relationship->value : "";
                $source = property_exists( $relationship, "source" ) ? $relationship->source : "woocommerce";

                echo $this->html->build_input( "wcField-" . $i . "-type", $sf_field["type"] );
                if ( empty( $sf_field["picklistValues"] ) ) {
                  echo $this->get_wc_select_element( $from, "wcField-" . $i, $selected, $required, $sf_field["type"] );

                  if ( $source === "custom" && !in_array( $type, array( "boolean", "date" ) ) ) {
                    $input_type = $type === "double" ? "number" : "text";
                    echo $this->html->build_input( "wcField-" . $i . "-custom", $value, $input_type );
                  }
                  echo $this->html->build_input( "wcField-" . $i . "-source", $source );
                } else {
                  echo $this->html->build_select( $sf_field["picklistValues"], "wcField-" . $i, "nwsi-wc-field", $selected, $required );
                  echo $this->html->build_input( "wcField-" . $i . "-source", "sf-picklist" );
                }
                ?>
              </td>
            </tr>
            <?php $i++; ?>
          <?php endforeach; ?>
        </tbody>
      </table>
      <button id="nwsi-add-new-relationship" class="button button-primary">+ <?php _e( "Add", "woocommerce-integration-nwsi" ); ?></button>
      <?php echo $this->html->build_input( "fields-count", $i ); ?>
      <?php
    }

    /**
     * Echo form part for adding unique fields for Salesforce object that user
     * is editing or creating.
     */
    private function display_unique_section() {
      ?>
      <div id="nwsi-unique-sf-fields-wrapper" class="nwsi-new-relationship-form-metabox">
        <h4><?php _e( "Unique fields", "woocommerce-integration-nwsi" ); ?></h4>
        <div id="nwsi-unique-sf-fields">
          <?php
          if ( empty( $this->unique_sf_fields ) ) {
            echo $this->html->build_select( $this->sf_fields, "uniqueSfField-0", "", "" );
          } else {
            for ( $i = 0; $i < count( $this->unique_sf_fields ); $i++ )  {
              echo $this->html->build_select( $this->sf_fields, "uniqueSfField-" . $i, "", $this->unique_sf_fields[ $i ] );
              if ( $i != count( $this->unique_sf_fields ) - 1 ) {
                echo "<span class='nwsi-unique-fields-plus'> + </span>";
              }
            }
          }
          ?>
        </div>
        <br/>
        <button id="nwsi-add-new-unique-sf-field" class="button button-primary">+ <?php _e( "Add", "woocommerce-integration-nwsi" ); ?></button>
      </div>
      <?php
    }

    /**
     * Echo select element for required objects section.
     *
     * @param  string  $name
     * @param  array   $sf_objects
     * @param  string  $selected_name
     * @param  string  $selected_id
     * @param  boolean $hidden        Defaults to false.
     * @return boolean                Is any option selected.
     */
    private function display_required_objects_select_element( $name, $sf_objects, $selected_name, $selected_id, $hidden = false ) {
      $is_selected = false;
      ?>
      <select class="<?php echo $hidden ? "hidden" : ""; ?>" name="<?php echo $name; ?>">
        <?php foreach( $sf_objects as $sf_object ): ?>
          <option
          <?php if ( $selected_id === $sf_object["id"] ): ?>
            selected
            <?php $is_selected = true; ?>
          <?php endif; ?>
          value="<?php echo $sf_object["name"] . "|" . $sf_object["label"] ."|" . $sf_object["id"]; ?>"><?php echo $sf_object["label"] . " (" . $sf_object["id"] . ")"; ?>
          </option>
        <?php endforeach; ?>
      </select>
      <?php
      return $is_selected;
    }

    /**
     * Echo form part for defining required object for sync object that user is
     * currently editing or creating.
     *
     * @param string  $to       Salesforce object that is being connected.
     * @param string  $to_label
     */
    private function display_required_objects_section( $to, $to_label ) {
      $sf_objects_raw = $this->sf->get_all_objects();
      $sf_objects = array();

      // prepare array of possible required salesforce objects
      foreach( $this->sf_fields as $sf_field ) {
        if ( $sf_field["type"] == "reference" && $sf_field["createable"] && !$sf_field["defaultedOnCreate"] ) {
          // user is by default included
          if( $sf_field["referenceTo"][0] != $to && $sf_field["referenceTo"][0] != "User" ) {
              array_push( $sf_objects, array(
                "name"  => $sf_field["referenceTo"][0],
                "label" => $sf_field["referenceTo"][0],
                "id"    => $sf_field["name"]
              ) );
          }
        }
      }
      foreach( $sf_objects_raw["sobjects"] as $sf_object_raw ) {
        if( !$sf_object_raw["deprecatedAndHidden"] && $sf_object_raw["createable"] && $sf_object_raw["updateable"] && $sf_object_raw["deletable"] ) {
          array_push( $sf_objects, array(
            "name"  => $sf_object_raw["name"],
            "label" => $sf_object_raw["label"],
            "id"    => $sf_object_raw["name"] . "Id"
          ) );
        }
      }

      $counter = 0;
      ?>
      <div id="nwsi-required-sf-objects-wrapper"  class="nwsi-new-relationship-form-metabox">
        <?php // for JS to copy and paste as new required object when users click add button ?>
        <?php $this->display_required_objects_select_element( "defaultRequiredSfObject", $sf_objects, "", "", true ); ?>
        <h4>
          <?php _e( "Required Salesforce objects", "woocommerce-integration-nwsi" ); ?>
        </h4>
        <table id="nwsi-required-sf-objects">
          <thead>
            <th><?php _e( "Active", "woocommerce-integration-nwsi" ); ?></th>
            <th><?php _e( "Object", "woocommerce-integration-nwsi" ); ?></th>
          </thead>
          <tbody>
            <?php foreach( $this->required_sf_fields as $required_sf_field ): ?>
              <tr>
                <td><input name="requiredSfObjectIsActive-<?php echo $counter; ?>" type="checkbox" checked></td>
                <td>
                <?php
                  $this->display_required_objects_select_element( "requiredSfObject-" . $counter, $sf_objects, $required_sf_field->name, $required_sf_field->id );
                  $counter++;
                ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <br/>
        <button id="nwsi-add-new-required-sf-object" class="button button-primary">+ <?php _e( "Add", "woocommerce-integration-nwsi" ); ?></button>
      </div>
      <br/>
      <?php
    }

    /**
     * Is the given salesforce field safe for users select element.
     *
     * @param  array  $field
     * @return boolean
     */
    private function is_safe_for_user_pick( $field ) {
      if ( ! $field["createable"] || $field["deprecatedAndHidden"] ) {
        return false;
      }
      if ( $field["type"] === "reference" ) {
        return false;
      }

      return true;
    }

    /**
     * Is given field required.
     * @param  array $field
     * @return boolean
     */
    private function is_required( $field ) {
      return ! $field["nillable"] && ! $field["defaultedOnCreate"];
    }

    /**
     * Append additional info to each field's label from given $fields.
     *
     * @param  array $fields
     * @return array
     */
    private function append_additional_info_to_label( $fields ) {
      for ( $i = 0; $i < count( $fields ); $i++ ) {
        $fields[ $i ]["label"] .= " (" . $fields[ $i ]["type"] .  ")";
        if ( $this->is_required( $fields[ $i ] ) ) {
          $fields[ $i ]["label"] .= "*";
        }
      }

      return $fields;
    }

    /**
     * Return select HTML element with given name and options from WooCommerce.
     *
     * @param  array    $field_names WooCommerce object field names.
     * @param  string   $name        Name of the select element.
     * @param  string   $selected    Name of the selected option.
     * @param  boolean  $required    Is select element required, defaults to false.
     * @param  string   $type        Type of corresponding SF field, defaults to "".
     * @return string                Representing HTML element.
     */
    private function get_wc_select_element( $from, $name, $selected, $required = false, $type = "" ) {
      $fields = array();
      foreach( $this->get_wc_object_description( $from ) as $field_name ) {
        array_push( $fields, array(
          "name"  => $field_name,
          "label" => ucwords( str_replace( "_", " ", $field_name ) )
        ) );
      }

      if ( !empty( $type ) ) {
        if ( in_array( $type, array( "string", "double", "integer", "url", "phone", "textarea" ) ) ) {
          array_push( $fields, array( "name" => "custom-value", "label" => "Custom value" ) );
        } else if ( $type == "date" ) {
          array_push( $fields, array( "name" => "custom-current-date", "label" => "Current Date" ) );
        } else if ( $type == "boolean" ) {
          array_push( $fields, array( "name" => "custom-true", "label" => "True" ) );
          array_push( $fields, array( "name" => "custom-false", "label" => "False" ) );
        }
      }
      return $this->html->build_select( $fields, $name, "", $selected, $required );
    }


    /**
     * Return NWSI model property keys (attribute names).
     *
     * @param  string $type Name of the model.
     * @return array
     */
    private function get_wc_object_description( string $type ) {
      switch( strtolower( $type ) ) {
        case "product":
        case "order product":
          $model = new NWSI_Product_Model();
          break;
        case "order":
          $model = new NWSI_Order_Model();
          break;
        case "order_item":
        case "order item":
          $model = new NWSI_Order_Item_Model();
          break;
        default:
          return null;
      }
      return $model->get_property_keys();
    }

  }
}
