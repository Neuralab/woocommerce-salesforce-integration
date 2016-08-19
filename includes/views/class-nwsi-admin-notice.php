<?php
if ( !defined( "ABSPATH" ) ) {
  exit;
}

if ( !class_exists( "NWSI_Admin_Notice" ) ) {
  class NWSI_Admin_Notice {

    /**
    * Display admin notice depending on the given status
    * @param string status
    */
    public function display_relationship_notice( $status ) {
      switch( $status ) {
        case "rel_new_success":
          add_action( "admin_notices", array( $this, "display_rel_new_success" ) );
          break;
        case "rel_new_fail":
          add_action( "admin_notices", array( $this, "display_rel_new_error" ) );
          break;
        case "rel_edit_success":
          add_action( "admin_notices", array( $this, "display_rel_edit_success" ) );
          break;
        case "rel_edit_fail":
          add_action( "admin_notices", array( $this, "display_rel_edit_error" ) );
          break;
        default:
          break;
      }
    }

    /**
    * Display admin notice for status obtained after attempt to get access token
    * @param string $status
    */
    public function display_access_token_notice( $status ) {
      switch( $status ) {
        case "access_token_error":
          add_action( "admin_notices", array( $this, "display_access_token_error" ) );
          break;
        case "instance_url_error":
          add_action( "admin_notices", array( $this, "display_instance_url_error" ) );
          break;
        case "success":
          add_action( "admin_notices", array( $this, "display_access_token_success" ) );
          break;
        default:
          add_action( "admin_notices", array( $this, "display_token_url_call_error" ) );
      }
    }

    /**
    * HTML for admin notice
    * @param string $status
    * @param string $type
    */
    private function display_admin_notice( $status, $type ) {
      ?>
      <div class="notice notice-<?php echo $type; ?> is-dismissible">
        <p><?php _e( $status, "woocommerce-integration-nwsi" ); ?></p>
      </div>
      <?php
    }

    /**
    * Admin notice HTML for successfully updating the relationship
    */
    public function display_rel_edit_success() {
      $this->display_admin_notice( "Relationship successfully edited!", "success" );
    }

    /**
    * Admin notice HTML for successfully creating the relationship
    */
    public function display_rel_new_success() {
      $this->display_admin_notice( "Relationship successfully created!", "success" );
    }

    /**
    * Admin notice HTML for failed relationship update
    */
    public function display_rel_edit_error() {
      $this->display_admin_notice( "Something went wrong while updating the relationship, please try again!", "error" );
    }

    /**
    * Admin notice HTML for failed creation of new relationship
    */
    public function display_rel_new_error() {
      $this->display_admin_notice( "Something went wrong while creating new relationship, please try again!", "error" );
    }

    /**
    * Admin notice HTML for successfully obtained tokens
    */
    public function display_access_token_success() {
      $this->display_admin_notice( "Access token successfully obtained!", "success" );
    }

    /**
    * Admin notice HTML for no access found error
    */
    public function display_access_token_error() {
      $this->display_admin_notice( "Access token missing from response, please try again!", "error" );
    }

    /**
    * Admin notice HTML for no instance url found error
    */
    public function display_instance_url_error() {
      $this->display_admin_notice( "Instance URL missing from response, please try again!", "error" );
    }

    /**
    * Admin notice HTML for failed response to access token request
    */
    public function display_token_url_call_error() {
      $this->display_admin_notice( "Call to token URL failed, please try again!", "error" );
    }

  }
}
