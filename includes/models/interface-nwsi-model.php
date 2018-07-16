<?php
if ( !defined( "ABSPATH" ) ) {
  exit;
}


/**
 * Common interface for all plugin's models.
 */
interface NWSI_Model {

  /**
   * Return all the property keys of the model.
   *
   * @return array
   */
  public function get_property_keys();

  /**
   * Return property value of the provided key.
   *
   * @param  string $key
   * @return string
   */
  public function get( $key );
}
