<?php
if ( !defined( "ABSPATH" ) ) {
  exit;
}

if ( !class_exists( "NWSI_Salesforce" ) ) {
  class NWSI_Salesforce {

    protected $access_token;

    /**
     * Make a call to the API
     * @param string  $url
     * @param boolean $authorization
     * @param string  $type - get (default) or post
     * @param string  $params
     * @param string  $content_type
     * @return array
     */
    public function get_response( $url, $authorization = true, $type = "get", $params = "", $content_type = "" ) {
      $http_header = array();
      $response = null;
      $args = array(
        "httpversion" => "1.0",
        "timeout"     => 5,
        "redirection" => 5,
        "blocking"    => true,
        "cookies"     => array()
      );

      if ( $authorization ) {
        $http_header["Authorization"] = "OAuth " . $this->access_token;
      }

      if ( $content_type != "" ) {
          $http_header["Content-Type"] = $content_type;
      }

      if ( !empty( $http_header ) ) {
          $args["headers"] = $http_header;
      }

      // get response
      switch( strtolower( $type ) ) {
        case "get":
          $args["method"] = "GET";
          if ( $params != "" ) {
            $url .= "?" . $params;
          }
          $response = wp_remote_get( $url, $args );
          break;
        case "post":
          $args["method"] = "POST";
          if ( $params != "" ) {
            $args["body"] = $params;
          }
          $response = wp_remote_post( $url, $args );
          break;
        case "delete":
          $args["method"] = "DELETE";
          if ( $params != "" ) {
            $args["body"] = $params;
          }
          $response = wp_remote_post( $url, $args );
          break;
        default:
          return array();
      }
      try {
        if ( is_array( $response ) && !empty( $response["body"] ) ) {
          return json_decode( $response["body"], true );
        } else {
          return array();
        }
      } catch( Exception $exc ) {
        return array();
      }
    }

  }
}
