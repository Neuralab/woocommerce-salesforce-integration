<?php
if ( !defined( "ABSPATH" ) ) {
  exit;
}


if ( !class_exists( "NWSI_Utility" ) ) {
  /**
   * Basic helper methods used in the plugin.
   */
  class NWSI_Utility {

    /**
    * Save content to the defined file. It is assumed that the $dir is located
    * in the plugin's root directory.
    *
    * @param string $filename
    * @param string $dir
    * @param string $content
    * @return boolean
    */
    public function save_to_file( $filename, $dir, $content ) {
      $filepath = NWSI_DIR_PATH . $dir . "/" . $filename;
      $handle = fopen( $filepath, "w" );

      fwrite( $handle, $content );
      fclose( $handle );

      return true;
    }

    /**
    * Load content from the file and, optionally, decode it from JSON.
    * It is assumed that the $dir is located in the plugin's root directory.
    *
    * @param string  $filename
    * @param string  $dir
    * @param boolean $json_decode Defaults to true.
    * @return string|array|object
    */
    public function load_from_file( $filename, $dir, $json_decode = true ) {
      $filepath = NWSI_DIR_PATH . $dir . "/" . $filename;
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
     * Filter raw keys extracted from the database for order, product, etc.
     *
     * @param  array $keys_raw
     * @return array
     */
    public function filter_meta_keys( $keys_raw ) {
      $keys = array();
      foreach ($keys_raw as $key_container) {
        $pos = strpos( $key_container[0], "_" );
        if ( $pos !== false ) {
          array_push( $keys, substr_replace( $key_container[0], "", $pos, strlen( "_" ) ) );
        } else {
          array_push( $keys, $key_container[0] );
        }
      }
      return $keys;
    }

  }
}
