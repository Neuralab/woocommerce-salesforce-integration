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

  }
}
