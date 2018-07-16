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
     * Initialize class attributes.
     * @param NWSI_Salesforce_Object_Manager $sf
     */
    public function __construct( NWSI_Salesforce_Object_Manager $sf ) {
      require_once ( NWSI_DIR_PATH . "includes/models/class-nwsi-order-model.php" );
      require_once ( NWSI_DIR_PATH . "includes/models/class-nwsi-order-item-model.php" );
      require_once ( NWSI_DIR_PATH . "includes/models/class-nwsi-product-model.php" );

      $this->sf = $sf;
    }

    /**
     * Echo form for editing the existing relationship provided as a parameter.
     * Existing relationship should contain properties:
     * [relationships]        => array
     * [from_object]          => string
     * [from_object_label]    => string
     * [to_object]            => string
     * [to_object_label]      => string
     * [required_sf_objects]  => array
     * [unique_sf_fields]     => array
     *
     * @param stdClass $rel Relationship object.
     */
    public function display_existing( stdClass $rel ) {
      $this->display_blank(
        $rel->from_object, $rel->from_object_label,
        $rel->to_object, $rel->to_object_label,
        json_decode( $rel->relationships ),
        json_decode( $rel->required_sf_objects ),
        json_decode( $rel->unique_sf_fields )
      );
    }

    /**
     * Echo HTML form for creating new relationships.
     *
     * @param string  $from                 WooCommerce object name.
     * @param string  $from_label           WooCommerce object label.
     * @param string  $to                   Salesforce object name.
     * @param string  $to_label             Salesforce object label.
     * @param array   $relationships        Existing relationship values, defaults to empty array.
     * @param array   $required_sf_objects  Array of required SF objects, defaults to empty array.
     * @param array   $unique_sf_fields     Array of unique SF fields, defaults to empty array.
     */
    public function display_blank( string $from, string $from_label, string $to, string $to_label, array $relationships = array(), array $required_sf_objects = array(), array $unique_sf_fields = array() ) {
      $wc_object_description = $this->get_wc_object_description( $from );
      $sf_object_description = $this->sf->get_object_description( $to );

      $this->display_title( empty( $relationships ) );

      $this->display_main_section(
        $from_label,
        $to_label,
        $sf_object_description,
        $wc_object_description,
        $relationships
      );

      $this->display_unique_section( $unique_sf_fields, $sf_object_description );
      $this->display_required_objects_section( $sf_object_description, $required_sf_objects, $to, $to_label );

      wp_enqueue_script( "nwsi-settings-js", NWSI_DIR_URL . "includes/js/nwsi-settings.js", array( "jquery" ) );
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
      <?php
    }

    /**
     * Echo main form inputs for editing existing or creating a new relationship.
     *
     * @param string  $from_label             WooCommerce object label.
     * @param string  $to_label               Salesforce object label.
     * @param array   $sf_object_description
     * @param array   $wc_object_description
     * @param array   $relationships          Existing relationship values.
     */
    private function display_main_section( string $from_label, string $to_label, array $sf_object_description, array $wc_object_description, array $relationships ) {
      ?>
      <table class="form-table" id="nwsi-new-relationship-form" >
        <thead>
          <th id="nwsi-from-object"><?php _e( $to_label, "woocommerce-integration-nwsi" ); ?> (Salesforce)</th>
          <th id="nwsi-to-object"><?php _e( $from_label, "woocommerce-integration-nwsi" ); ?> (WooCommerce)</th>
        </thead>
        <tbody>
        <?php $i = 0; ?>
        <?php foreach( $sf_object_description["fields"] as $sf_field ):
          // we don't need to create inputs for other object IDs
          if ( !$sf_field["createable"]
              || $sf_field["deprecatedAndHidden"]
              || $sf_field["type"] == "reference" ) {
            continue;
          }
        ?>
          <tr valign="top" >
            <td scope="row" class="titledesc">
              <?php
              // input labels
              $label_text = $sf_field["label"] . " (";
              $label_text .= $sf_field["type"] .  ")";

              $required = false;
              if ( !$sf_field["nillable"] && !$sf_field["defaultedOnCreate"] ) {
                $label_text .= " <span class='nwsi-required-sf-field' >*</span>";
                $required = true;
              }
              ?>
              <label for="<?php echo "wcField-" . $i ?>"><?php echo $label_text; ?></label>
              <input type="hidden" name="<?php echo "sfField-" . $i; ?>" value="<?php echo $sf_field["name"]; ?>" />
            </td>
            <td class="forminp">
              <?php
              $selected = $source = $type = $value = "";
              foreach ( $relationships as $relationship ) {
                if ( $relationship->to == $sf_field["name"] ) {

                  if ( $relationship->from == "salesforce" ) {
                    $selected = $relationship->value;
                  } else if ( $relationship->from == "custom" ) {
                    if ( $relationship->type == "date" ) {
                      $selected = "custom-current-date";
                    } else if ( $relationship->type == "boolean" ) {
                      $selected = "custom-" . $relationship->value;
                    } else {
                      $selected = "custom-value";
                    }
                  } else {
                    $selected = $relationship->from;
                  }

                  if ( property_exists( $relationship, "source" ) ) {
                    $source = $relationship->source;
                  } else {
                    $source = "woocommerce";
                  }

                  if ( property_exists( $relationship, "type" ) ) {
                    $type = $relationship->type;
                  }

                  if ( property_exists( $relationship, "value" ) ) {
                    $value = $relationship->value;
                  }
                  break;
                }
              }
              ?>
              <input type="hidden" name="<?php echo "wcField-" . $i . "-type"; ?>" value="<?php echo $sf_field["type"]; ?>" />
              <?php
              if ( empty( $sf_field["picklistValues"] ) ) {

                echo $this->generate_wc_select_element( $wc_object_description, "wcField-" . $i, $selected, $required, $sf_field["type"] );

                if ( $source == "custom" && !in_array( $type, array( "boolean", "date" ) ) ) {
                  $input_type = ( $type == "double" ) ? "number" : "text";
                  ?>
                  <br/>
                  <input type="<?php echo $input_type; ?>" name="<?php echo "wcField-" . $i . "-custom"; ?>" value="<?php echo $value ?>" />
                  <?php
                }

                ?>
                <input type="hidden" name="<?php echo "wcField-" . $i . "-source"; ?>" value="<?php echo $source; ?>" />
                <?php
              } else {
                echo $this->generate_sf_picklist_select_element( $sf_field["picklistValues"], "wcField-" . $i, $selected, $required );
                ?>
                <input type="hidden" name="<?php echo "wcField-" . $i . "-source"; ?>" value="sf-picklist" />
                <?php
              }
              ?>
            </td>
          </tr>

        <?php $i++; endforeach; ?>
        <?php $numOfFields = $i; ?>
        </tbody>
      </table>
      <input type="hidden" name="numOfFields" value="<?php echo $numOfFields; ?>" />
      <?php
    }

    /**
     * Echo form part for adding unique fields for Salesforce object that user
     * is editing or creating.
     *
     * @param array $unique_sf_fields
     * @param array $sf_object_description
     */
    private function display_unique_section( array $unique_sf_fields, array $sf_object_description ) {
      ?>
      <div id="nwsi-unique-sf-fields-wrapper" class="nwsi-new-relationship-form-metabox">
        <h4>Unique fields</h4>
        <div id="nwsi-unique-sf-fields">

          <?php
          if ( empty( $unique_sf_fields ) ) {
            echo $this->generate_sf_select_element( $sf_object_description["fields"], "uniqueSfField-0", "" );
          } else {
            for ( $i = 0; $i < sizeof( $unique_sf_fields ); $i++ )  {
              echo $this->generate_sf_select_element( $sf_object_description["fields"], "uniqueSfField-" . $i, $unique_sf_fields[$i] );
              if ( $i != sizeof( $unique_sf_fields ) - 1 ) {
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
    private function display_required_objects_select_element( string $name, array $sf_objects, string $selected_name, string $selected_id, bool $hidden = false ) {
      $is_selected = false;
      $class = "";
      if ( $hidden ) {
        $class .= "hidden";
      }
      ?>
      <select class="<?php echo $class; ?>" name="<?php echo $name; ?>">
        <?php foreach( $sf_objects as $sf_object ): ?>
          <option
          <?php if ( $selected_id == $sf_object["id"] ): ?>
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
     * @param array   $sf_object_description
     * @param array   $required_sf_objects
     * @param string  $to                     Salesforce object that is being connected.
     * @param string  $to_label
     */
    private function display_required_objects_section( array $sf_object_description, array $required_sf_objects, string $to, string $to_label ) {
      $sf_objects_raw = $this->sf->get_all_objects();
      $sf_objects = array();

      // prepare array of possible required salesforce objects
      foreach( $sf_object_description["fields"] as $sf_field ) {
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
            <?php foreach( $required_sf_objects as $required_sf_object ): ?>
              <tr>
                <td><input name="requiredSfObjectIsActive-<?php echo $counter; ?>" type="checkbox" checked></td>
                <td>
                <?php
                  $this->display_required_objects_select_element( "requiredSfObject-" . $counter, $sf_objects, $required_sf_object->name, $required_sf_object->id );
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
     * Return select HTML element with given name and options from Salesforce.
     *
     * @param  array   $fields    Salesforce object fields.
     * @param  string  $name      Name of the select element.
     * @param  string  $selected  Name of the selected option.
     * @return string             Representing HTML element.
     */
    private function generate_sf_select_element( array $fields, string $name, string $selected ) {
      return $this->generate_select_element( $fields, $name, $selected, true );
    }

    /**
     * Return select HTML element with given options from the SF field picklist.
     *
     * @param  array   $fields   Salesforce picklist fields.
     * @param  string  $name     Name of the select element.
     * @param  string  $selected Value of the selected option/field.
     * @param  boolean $required Is select element required, defaults to false.
     * @return string            Representing HTML select element.
     */
    private function generate_sf_picklist_select_element( array $fields, string $name, string $selected, bool $required = false ) {
      return $this->generate_select_element( $fields, $name, $selected, false, $required );
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
    private function generate_wc_select_element( array $field_names, string $name, string $selected, bool $required = false, string $type = "" ) {
      $fields = array();
      foreach( $field_names as $field_name ) {
        array_push( $fields, array(
          "name"  => $field_name,
          "label" => ucwords( str_replace( "_", " ", $field_name ) )
        ) );
      }
      return $this->generate_select_element( $fields, $name, $selected, false, $required, $type );
    }

    /**
     * Return an option HTML element as a string.
     *
     * @param  string  $name
     * @param  string  $value
     * @param  boolean $is_selected
     * @return string
     */
    private function generate_option_element( string $name, string $value, bool $is_selected = false ) {
      $option_element = "<option value='" . $value . "'";
      if ( $is_selected ) {
        $option_element .= " selected ";
      }
      return $option_element . ">" . $name . "</option>";
    }

    /**
     * Return select HTML element
     * @param  array    $fields            Array of objects with option names and values.
     * @param  string   $name              Name of the select element.
     * @param  string   $selected          Name of the selected option, defaults to "".
     * @param  boolean  $ignore_refrences  True to ignore references fields, defaults to false.
     * @param  boolean  $required          Is select element required, defaults to false.
     * @param  string   $type              Type of corresponding SF field, defaults to "".
     * @return string                     Representing HTML element.
     */
    private function generate_select_element( array $fields, string $name, string $selected = "", bool $ignore_refrences = false, bool $required = false, string $type = "" ) {

      $select_element = "<select";
      if ( $required ) {
        $select_element .= " required ";
      }
      $select_element .= " id='" . $name . "' name='". $name . "' >";
      $select_element .= "<option value=''>None</option>";

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

      foreach( $fields as $field ) {
        // field check
        if ( $ignore_refrences ) {
          if ( !$field["createable"] || $field["deprecatedAndHidden"] || $field["type"] == "reference" ) {
            continue;
          }
        }

        if( array_key_exists( "active", $field ) && !$field["active"] ) {
          continue;
        }

        if ( array_key_exists( "value", $field ) && !array_key_exists( "name", $field ) ) {
          $field["name"] = $field["value"];
        }

        if ( $selected == $field["name"] ) {
          $select_element .= $this->generate_option_element( $field["label"], $field["name"], true );
        } else {
          $select_element .= $this->generate_option_element( $field["label"], $field["name"]);
        }
      }

      $select_element .= "</select>";
      return $select_element;
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
