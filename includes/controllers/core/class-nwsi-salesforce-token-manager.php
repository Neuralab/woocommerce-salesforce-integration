<?php
if ( !defined( "ABSPATH" ) ) {
  exit;
}
if ( !class_exists( "NWSI_Salesforce" ) ) {
  require_once( "class-nwsi-salesforce.php" );
}

if ( !class_exists( "NWSI_Salesforce_Token_Manager" ) ) {
  class NWSI_Salesforce_Token_Manager extends NWSI_Salesforce {

    protected $refresh_token;
    protected $instance_url;
    protected $consumer_key;
    protected $consumer_secret;
    protected $redirect_uri;

    private $cryptor;

    protected $login_uri;

    /**
     * Class constructor
     */
    public function __construct() {

      $this->login_uri = get_option("woocommerce_nwsi_login_url");

      require_once ( NWSI_DIR_PATH . "includes/controllers/utilites/class-nwsi-cryptor.php" );
      $this->cryptor = new NWSI_Cryptor();

      $this->redirect_uri   = admin_url(esc_attr__("admin.php?page=wc-settings&tab=integration&section=nwsi"), "https");
      $this->access_token   = $this->load_access_token();
      $this->refresh_token  = $this->load_refresh_token();
      $this->instance_url   = get_option( "woocommerce_nwsi_instance_url" );


      $settings = get_option( "woocommerce_nwsi_settings", null );
      if ( !empty( $settings ) ) {
        $this->consumer_key     = $settings["consumer_key"];
        $this->consumer_secret  = $settings["consumer_secret"];
      }
    }

    /**
     * Revalidate token fron Salesforce API
     * @return boolean - true in case of succesful revalidation
     */
    protected function revalidate_token() {
      $params = "grant_type=refresh_token" .
      "&client_id=" . urlencode( $this->consumer_key ) .
      "&client_secret=" . urlencode( $this->consumer_secret ) .
      "&refresh_token=" . urlencode( $this->refresh_token );

      $url = $this->login_uri . "/services/oauth2/token";
      $response = $this->get_response( $url, false, "post", $params );
      if ( !isset( $response["access_token"] ) || empty( $response["access_token"] )
      || !isset( $response["instance_url"] ) || empty( $response["instance_url"] ) ) {
        return false;
      }

      $this->access_token = $response["access_token"];
      $this->instance_url = $response["instance_url"];

      $this->save_access_token( $this->access_token );
      update_option( "woocommerce_nwsi_instance_url", $this->instance_url );

      return true;
    }

    /**
     * Call Salesforce API with provided code and saves obtained instance url,
     * access and refresh token in DB
     * @param string $code
     * @return string (access_token_error | instance_url_error | success)
     */
    public function get_access_token( $code ) {
      $url = $this->login_uri . "/services/oauth2/token";

      $params = "code=" . $code
      . "&grant_type=authorization_code"
      . "&client_id=" . $this->consumer_key
      . "&client_secret=" . $this->consumer_secret
      . "&redirect_uri=" . urlencode( $this->redirect_uri );

      $response = $this->get_response( $url, false, "get", $params );

      if ( !isset( $response["access_token"] ) || empty( $response["access_token"] ) ) {
        return "access_token_error";
      }

      if ( !isset( $response["instance_url"] ) || empty( $response["instance_url"] ) ) {
        return "instance_url_error";
      }

      $this->access_token   = $response["access_token"];
      $this->refresh_token  = $response["refresh_token"];
      $this->instance_url   = $response["instance_url"];

      $this->save_access_token( $this->access_token );
      $this->save_refresh_token( $this->refresh_token );
      update_option( "woocommerce_nwsi_instance_url", $this->instance_url );

      $this->update_connection_hash();
      return "success";
    }

    /**
     * Create or update a hash string that identifies current used connection
     */
    private function update_connection_hash() {
      $connection_hash = wp_hash( $this->consumer_key . $this->consumer_secret . $this->login_uri );
      update_option( "woocommerce_nwsi_connection_hash", $connection_hash );
    }

    /**
     * Connection string is valid if consumer key, secret, and login URL are the
     * same as one when connection hash were created
     * @return boolean
     */
    public function is_connection_hash_valid() {
      $new_connection_hash = wp_hash( $this->consumer_key . $this->consumer_secret . $this->login_uri );
      $old_connection_hash = get_option( "woocommerce_nwsi_connection_hash" );

      return $new_connection_hash === $old_connection_hash;
    }

    /**
     * Set login URI used for token management
     * @param string $login_uri
     */
    public function set_login_uri( $login_uri ) {
      if ( !empty( $login_uri ) && is_string( $login_uri ) ) {
        $this->login_uri = $login_uri;
      }
    }

    /**
     * Return Salesforce authentication page URL
     * @param string $consumer_key
     * @param string $consumer_secret
     */
    public function redirect_to_salesforce( $consumer_key, $consumer_secret ) {

      if ( empty( $consumer_key ) || empty( $consumer_secret ) ) {
        return "";
      }
      if ( $this->is_connection_hash_valid() ) {
  		  return "";
  		}

      return $this->get_oauth_url( $consumer_key );
    }

    /**
     * Return URL string required for obtaining access token
     * @param string $consumer_key
     * @return string
     */
    private function get_oauth_url( $consumer_key ) {
      return $this->login_uri
        . "/services/oauth2/authorize?response_type=code&client_id="
        . $consumer_key . "&redirect_uri=" . urlencode( $this->redirect_uri );
    }

    /**
     * Escape special characters for SOQL
     * @param string $str
     * @return string
     */
    protected function soql_string_literal( $str ) {
      // ? & | ! { } [ ] ( ) ^ ~ * : \ " ' + -
      $characters  = array(
        '\\', '?' , '&' , '|' , '!' , '{' , '}' , '[' , ']' , '(' , ')' , '^' , '~' , '*' , ':' , '"' , '\'', '+' , '-'
      );
      $replacement = array(
        '\\\\', '\?', '\&', '\|', '\!', '\{', '\}', '\[', '\]', '\(', '\)', '\^', '\~', '\*', '\:', '\"', '\\\'', '\+', '\-'
      );
      return str_replace( $characters, $replacement, $str );
    }

    /**
     * Return true if it has access token. Doesn't mean that it's valid.
     * @return boolean
     */
    public function has_access_token() {
      $access_token = $this->load_access_token();
      if ( empty( $access_token ) ) {
        return false;
      } else if ( is_bool( $access_token ) ) {
        return false;
      } else {
        return true;
      }
    }

    /**
     * Load and decrypt access token from database
     * @return mixed - false if failed or string
     */
    public function load_access_token() {
      return $this->cryptor->decrypt( get_option( "woocommerce_nwsi_access_token" ), true );
    }

    /**
     * Encrypt and save access token to database
     * @param string $access_token
     */
    public function save_access_token( $access_token ) {
      $crypted_token = $this->cryptor->encrypt( $access_token, true );
      update_option( "woocommerce_nwsi_access_token", $crypted_token );
    }

    /**
     * Load and decrypt refresh token from database
     * @return mixed - false if failed or string
     */
    public function load_refresh_token() {
      return $this->cryptor->decrypt( get_option( "woocommerce_nwsi_refresh_token" ), true );
    }

    /**
     * Encrypt and save refresh token to database
     * @param string $refresh_token
     */
    public function save_refresh_token( $refresh_token ) {
      $crypted_token = $this->cryptor->encrypt( $refresh_token, true );
      update_option( "woocommerce_nwsi_refresh_token", $crypted_token );
    }


  }
}
