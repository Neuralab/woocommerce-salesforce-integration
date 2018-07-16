<?php
if ( !defined( "ABSPATH" ) ) {
  exit;
}

if ( !class_exists( "NWSI_Cryptor" ) ) {
  class NWSI_Cryptor {

    private $key;

    /**
    * Class constructor, use NWSI_KEY defined in wp-config.php or hardcoded one
    */
    function __construct() {
      require_once( NWSI_DIR_PATH . "includes/libs/crypto/Crypto.php" );
      // changing the key will force user to reauthenticate (rewrite DB entries)
      $plain_key = ( defined( "NWSI_KEY" ) ) ? NWSI_KEY : "_,@Lh|Xe^HT6(Spc[!-__D";
      $this->key = unpack( "H*", mb_strimwidth( $plain_key, 0, 8 ) )[1];
    }

    /**
    * Return encrypted data or false in case of failure
    * @param string  $data
    * @param boolean $encode - set to true for base64 encoding
    * @return mixed - boolean or string
    */
    public function encrypt( $data, $encode = false ) {
      try {

        $encrypted_data = Crypto::Encrypt( $data, $this->key );

        if ( $encode ) {
          return base64_encode( $encrypted_data );
        } else {
          return $encrypted_data;
        }

      } catch ( Exception $ex ) {
        return false;
      }
    }

    /**
    * Return decrypted data or false in case of failure
    * @param string  $data
    * @param boolean $encoded - set to true if $data is base64 encoded
    * @return mixed
    */
    public function decrypt( $data, $encoded = false ) {
      try {
        if ( $encoded ) {
          $data = base64_decode( $data );
        }
        return Crypto::Decrypt( $data, $this->key );

      } catch ( Exception $ex ) {
        return false;
      }
    }
  }
}
