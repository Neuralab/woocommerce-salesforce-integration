<?php
if ( !defined( "ABSPATH" ) ) {
  exit;
}

if ( !class_exists( "NWSI_Settings" ) ) {
  class NWSI_Settings extends WC_Integration {

    /**
     * @var NWSI_Salesforce_Object_Manager
     */
    private $sf;

    /**
     * @var NWSI_DB
     */
    private $db;

    /**
     * @var NWSI_Relationships_Table
     */
    private $relationships_table;

    /**
     * @var NWSI_Utility
     */
    private $utility;

    /**
     * @var NWSI_Admin_Notice
     */
    private $admin_notice;

    /**
     * Class constructor, initialize attributes values and process requests if any.
     */
    public function __construct() {
      require_once ( "class-nwsi-relationship-form.php" );
      require_once ( "class-nwsi-relationships-table.php" );
      require_once ( "class-nwsi-admin-notice.php" );
      require_once ( NWSI_DIR_PATH . "includes/controllers/core/class-nwsi-db.php" );
      require_once ( NWSI_DIR_PATH . "includes/controllers/utilites/class-nwsi-utility.php" );

      global $woocommerce;

      $this->id           = "nwsi";
      $this->method_title = __( "Salesforce", "woocommerce-integration-nwsi" );

      $this->sf           = new NWSI_Salesforce_Object_Manager();
      $this->db           = new NWSI_DB();
      $this->utility      = new NWSI_Utility();
      $this->admin_notice = new NWSI_Admin_Notice();

      $this->process_requests();
    }

    /**
     * Process GET and POST requests.
     */
    private function process_requests() {
      if ( !array_key_exists( "rel", $_GET ) ) {
        if ( !empty( $_POST["save"] ) ) {
          $this->update_additional_settings($_POST);

          if ( array_key_exists( "woocommerce_nwsi_consumer_secret", $_POST )
            && array_key_exists( "woocommerce_nwsi_consumer_key", $_POST ) ) {

            $redirect_uri = $this->sf->redirect_to_salesforce(
              $_POST["woocommerce_nwsi_consumer_key"],
              $_POST["woocommerce_nwsi_consumer_secret"]
            );

            if ( !empty( $redirect_uri ) ) {
              header( "Location: " . $redirect_uri );
            }
          }
        }

        if ( array_key_exists( "code", $_GET ) && !empty( $_GET["code"] ) ) {
          $redirect_uri = $this->obtain_access_token( $_GET["code"] );
          header( "Location: " . $redirect_uri );
        }

        $this->init_form_fields();
        $this->init_settings();

        if ( array_key_exists( "status", $_GET ) && !empty( $_GET["status"] ) ) {
          if ( !empty( $_GET["source"] ) && $_GET["source"] == "access_token" ) {
            $this->admin_notice->display_access_token_notice( $_GET["status"] );
          } else {
            $this->admin_notice->display_relationship_notice( $_GET["status"] );
          }
        }

        add_action( "woocommerce_update_options_integration_" . $this->id, array( $this, "process_admin_options" ) );
      } else if ( !empty( $_GET["rel"] ) && !empty( $_POST ) ) {

        $status = $this->manage_relationship_process( $_GET["rel"] );
        $redirect_uri = admin_url("admin.php", "https")
          . "?page=" . $_GET["page"] . "&tab=" . $_GET["tab"]
          . "&section=" . $_GET["section"] . "&status=" . $status;

        header( "Location: " . $redirect_uri );
      }
    }

    /**
     * Update options such as automatic order sync and login URL.
     *
     * @param array $data Usually $_POST.
     */
    private function update_additional_settings( $data ) {
      // update automatic order sync option
      if ( empty( $data["automatic_order_sync"] ) ) {
        update_option( "woocommerce_nwsi_automatic_order_sync", "0" );
      } else {
        update_option( "woocommerce_nwsi_automatic_order_sync", "1" );
      }
      // update login url
      if ( !empty( $data["woocommerce_nwsi_login_url"] ) ) {
        $login_url = $data["woocommerce_nwsi_login_url"];
        if ( substr( $login_url, -1 ) === "/" ) {
          $login_url = esc_attr__( rtrim( trim( $login_url ), "/" ) );
        }
        $this->sf->set_login_uri( $login_url );
        update_option( "woocommerce_nwsi_login_url", $login_url );
      }
    }

    /**
     * Save new or update existing relationship.
     *
     * @param string $rel_type  Type of relationship (new or existing).
     * @return string           Status.
     */
    private function manage_relationship_process( $rel_type ) {
      $status = "";

      if ( $rel_type == "new" ) {
        $response = $this->db->save_new_relationship( $_GET["from"], $_GET["from_label"], $_GET["to"], $_GET["to_label"], $_POST );
        if ( $response ) {
          $status = "rel_new_success";
        } else {
          $status = "rel_new_fail";
        }
      } else if ( $rel_type == "existing" ) {
        $response = $this->db->update_relationship( $_GET["key"], $_POST );
        if ( $response ) {
          $status = "rel_edit_success";
        } else {
          $status = "rel_edit_fail";
        }
      }
      return $status;
    }

    /**
     * Obtain access token and display corresponding admin notice.
     *
     * @param string $code  Obtained after user login.
     * @return string       Redirect URL.
     */
    private function obtain_access_token( $code ) {
      $status = $this->sf->get_access_token( $code );

      $redirect_uri = admin_url( "admin.php", "https" )
        . "?page=" . $_GET["page"] . "&tab=" . $_GET["tab"]
        . "&section=" . $_GET["section"]
        . "&status=" . $status . "&source=access_token";

      return $redirect_uri;
    }

    /**
     * Display form for choosing Salesforce and WooCommerce objects for
     * new relationship.
     */
    private function display_add_new_relationship_form() {
      $sf_objects = $this->sf->get_all_objects();
      ?>
      <table class="form-table" id="nwsi-new-relationship-form" >
        <tbody>
          <tr valign="top" >
            <th scope="row" class="titledesc">
              <label for="nwsi-rel-to-sf">Salesforce</label>
            </th>
            <td class="forminp">
              <select id="nwsi-rel-to-sf">
                <?php foreach( $sf_objects["sobjects"] as $sf_object ): ?>
                  <?php if( !$sf_object["deprecatedAndHidden"] && $sf_object["createable"] && $sf_object["updateable"] && $sf_object["deletable"] ): ?>
                    <option value="<?php echo $sf_object["name"]; ?>"><?php echo $sf_object["label"]; ?></option>
                  <?php endif; ?>
                <?php endforeach; ?>
              </select>
          </td>
          </tr>
          <tr valign="top">
            <th scope="row" class="titledesc">
              <label for="nwsi-rel-from-wc">WooCommerce</label>
            </th>
            <td class="forminp">
              <select id="nwsi-rel-from-wc">
                <option value="order">
                  <?php _e( "Order", "woocommerce-integration-nwsi" ); ?>
                </option>
                <option value="order_item">
                  <?php _e( "Order Item", "woocommerce-integration-nwsi" ); ?>
                </option>
                <option value="product">
                  <?php _e( "Product", "woocommerce-integration-nwsi" ); ?>
                </option>
              </select>
            </td>
          </tr>
          <tr>
            <th id="nwsi-add-new-rel-wrapper">
              <button id="nwsi-add-new-rel" class="button button-primary">
                + <?php _e( "Add", "woocommerce-integration-nwsi" ); ?>
              </button>
            </th>
          </tr>
        </tbody>
      </table>
      <?php
    }

    /**
     * Echo checkboxes for managing automatic order and product sync.
     */
    private function display_automatic_sync_settings() {
      $is_checked = "";
      if ( !empty( get_option( "woocommerce_nwsi_automatic_order_sync" ) ) ) {
        $is_checked = "checked";
      }
      ?>
      <div id="nwsi-sync-settings" >
        <label for="automatic-order-sync"><?php _e( "Automatic order sync", "woocommerce-integration-nwsi" ); ?></label>
        <input id="automatic-order-sync" name="automatic_order_sync" type="checkbox" <?php echo $is_checked; ?> />
      </div>
      <?php
    }

    /**
     * Echo default settings page for this plugin.
     */
    private function display_default_settings_page() {
      ?>
      <hr/>
      <h3><?php _e( "New relationship", "woocommerce-integration-nwsi" ); ?></h3>
      <?php $this->display_add_new_relationship_form(); ?>

      <hr/>
      <h3><?php _e( "Relationships", "woocommerce-integration-nwsi" ); ?></h3>
      <?php $this->display_automatic_sync_settings(); ?>
      <br/>
      <div id="nwsi-rels">
        <?php
        $this->relationships_table = new NWSI_Relationships_Table();
        $this->relationships_table->process_bulk_action();
        $relationships = $this->db->get_relationships();

        $date_time_format = get_option( "date_format" ) . " " . get_option( "time_format" );
        $data = array();
        foreach( $relationships as $relationship ) {
          $temp = array();

          $temp["id"]           = $relationship->id;
          $temp["date-created"] = date( $date_time_format, strtotime( $relationship->date_created ) );
          $temp["date-updated"] = date( $date_time_format, strtotime( $relationship->date_updated ) );
          $temp["active"]       = ( intval( $relationship->active ) == 1 ) ? "Yes" : "No";
          $temp["relationship"] = "<a href='" . admin_url("admin.php", "https")
          . "?page=wc-settings&tab=integration&section=nwsi"
          . "&rel=existing&key=" . $relationship->hash_key . "'>" . "<b>"
          . $relationship->to_object_label . " - " . $relationship->from_object_label . "</b> </a>";

          array_push( $data, $temp );
        }

        $this->relationships_table->set_items( $data );
        $this->relationships_table->prepare_items();
        $this->relationships_table->display();

        ?>
      </div>
      <br/>
      <?php
    }

    /**
     * Setup the gateway settings screen.
     *
     * @override
     */
    public function admin_options() {
      if ( isset( $_GET["rel"] ) && !empty( $_GET["rel"] ) ) {
        $this->relationship_form = new NWSI_Relationships_Form( $this->sf );
        if ( isset( $_GET["from"] ) && !empty( $_GET["from"] ) && isset( $_GET["to"] ) && !empty( $_GET["to"] ) ) {
          // form for new relationship
          if ( isset( $_GET["from_label"] ) && !empty( $_GET["from_label"] ) && isset( $_GET["to_label"] ) && !empty( $_GET["to_label"] ) ) {
            $from_label = esc_sql( $_GET["from_label"] );
            $to_label   = esc_sql( $_GET["to_label"] );
          } else {
            $from_label = esc_sql( $_GET["from"] );
            $to_label   = esc_sql( $_GET["to"] );
          }

          $to   = esc_sql( $_GET["to"] );
          $from = esc_sql( $_GET["from"] );
          $this->relationship_form->display_blank( $from, $from_label, $to, $to_label );

        } else if ( isset( $_GET["key"] ) && !empty( $_GET["key"] ) ) {
          // form for already existing relationship
          $key = esc_sql( $_GET["key"] );
          $this->relationship_form->display_existing( $this->db->get_relationship_by_key( $key ) );
        }
      } else {
        // default view
        parent::admin_options();
        $this->display_additional_settings_fields();

        if ( $this->sf->has_access_token() ) {
          $this->display_default_settings_page();
        }
      }
    }

    /**
     * Echo HTML for additional settings fields such as callback and login URL
     */
    private function display_additional_settings_fields() {
      $login_url = get_option( "woocommerce_nwsi_login_url" );
      if ( !$login_url ) {
        $login_url = "";
      }
      ?>
      <table class="form-table">
        <tr valign="top">
          <th scope="row" class="titledesc">
            <label for="woocommerce_nwsi_login_url"><?php _e( "Login URL", "woocommerce-integration-nwsi" ); ?></label>
          </th>
          <td class="forminp">
            <fieldset>
              <legend class="screen-reader-text"><span><?php _e( "Login URL", "woocommerce-integration-nwsi" ); ?></span></legend>
              <input class="input-text regular-input" id="woocommerce_nwsi_login_url" name="woocommerce_nwsi_login_url" type="text" value="<?php echo $login_url; ?>" >
            </fieldset>
          </td>
        </tr>
        <tr valign="top">
          <th scope="row" class="titledesc">
            <label for="woocommerce_nwsi_callback_url"><?php _e( "Callback URL", "woocommerce-integration-nwsi" ); ?></label>
          </th>
          <td class="forminp">
            <fieldset>
              <legend class="screen-reader-text"><span><?php _e( "Callback URL", "woocommerce-integration-nwsi" ); ?></span></legend>
              <input class="input-text regular-input" id="woocommerce_nwsi_callback_url" type="text" disabled value="<?php echo admin_url(esc_attr__("admin.php?page=wc-settings&tab=integration&section=nwsi"), "https"); ?>" >
            </fieldset>
          </td>
        </tr>
      </table>
      <?php
    }

    /**
     * Define settings form fields
     * @override
     */
    public function init_form_fields() {
      $this->form_fields = array(
        "consumer_key" => array(
          "title"   => __( "Consumer key", "woocommerce-integration-nwsi" ),
          "type"    => "text",
          "default" => ""
        ),
        "consumer_secret" => array(
          "title"   => __( "Consumer secret", "woocommerce-integration-nwsi" ),
          "type"    => "password",
          "default" => ""
        )
      );
    }

  }
}
