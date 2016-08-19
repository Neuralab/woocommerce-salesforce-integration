<?php
if ( !defined( "ABSPATH" ) ) {
  exit;
}


/**
 * Basic methods used in different plugins classes
 */
if ( !class_exists( "NWSI_Utility" ) ) {
  class NWSI_Utility {

    /**
    * Saves content to defined file
    * @deprecated
    * @param string $filename
    * @param string foldername
    * @param string $content
    * @return boolean
    */
    public function save_to_file( $filename, $foldername, $content ) {
      $filepath = ABSPATH . "wp-content/plugins/" . NWSI_FOLDER_NAME . "/". $foldername . "/" . $filename;
      $handle = fopen( $filepath, "w" );

      fwrite( $handle, $content );
      fclose( $handle );

      return true;
    }

    /**
    * Load content from file
    * @param string  $filename
    * @param string  $foldername
    * @param boolean $json_decode - default=true
    * @return mixed string or array/object if $json_decode=true
    */
    public function load_from_file( $filename, $foldername, $json_decode = true ) {
      if ( defined( NWSI_FOLDER_NAME ) ) {
        $root_foldername = NWSI_FOLDER_NAME;
      } else {
        $root_foldername = "woocommerce-salesforce-integration";
      }

      $filepath = ABSPATH . "wp-content/plugins/" . $root_foldername . "/" . $foldername . "/" . $filename;
      $handle = fopen( $filepath, "r" );

      $filesize = filesize( $filepath );
      if ( empty( $filesize ) ) {
        $filesize = filesize( $filename );
      }

      $content = fread( $handle, $filesize );

      fclose( $handle );

      if ( $json_decode ) {
        return json_decode( $content );
      }
      return $content;
    }

    /**
    * Return used protocol, HTTP or HTTPS
    * @return string
    */
    public function get_sites_http_protocol() {
      if ( isset( $_SERVER["HTTPS"] ) && ( $_SERVER["HTTPS"] == "on" || $_SERVER["HTTPS"] == 1 ) ||
      isset( $_SERVER["HTTP_X_FORWARDED_PROTO"] ) && $_SERVER["HTTP_X_FORWARDED_PROTO"] == "https" ) {
        return "https://";
      } else {
        return "http://";
      }
    }

  }
}
