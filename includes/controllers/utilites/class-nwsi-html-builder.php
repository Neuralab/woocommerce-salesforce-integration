<?php
if ( !defined( "ABSPATH" ) ) {
  exit;
}

if ( !class_exists( "NWSI_HTML_Builder" ) ) {
  class NWSI_HTML_Builder {

    /**
     * If any protected, private or non-existing method is called, it returns
     * it's content.
     *
     * @param  string $method Name of the called method.
     * @param  array  $args   Arguments provided within call.
     * @return string
     */
    public function __call( $method, $args ) {
      if ( method_exists( $this, $method ) ) {
        ob_start();
        call_user_func_array( array( $this, $method ), $args );
        $element = ob_get_contents();
        ob_end_clean();
        return $element;
      }
    }

    /**
     * Display select HTML element.
     *
     * @param  array    $options      Array of objects with option names and values.
     * @param  string   $name         Name of the select element.
     * @param  string   $class        Element's class.
     * @param  string   $selected     Name of the selected option, defaults to "".
     * @param  boolean  $required     Is select element required, defaults to false.
     * @param  boolean  $include_none Include option with empty value, defaults to true.
     * @param  array    $options_data Array which holds data-{key}="value" entries for each option.
     * @return void
     */
    protected function build_select( $options, $name, $class = "", $selected = "", $required = false, $include_none = true, $options_data = array() ) {
      ?>
      <select
        id="<?php esc_attr_e( $name ); ?>"
        name="<?php esc_attr_e( $name ); ?>"
        class="<?php esc_attr_e( $class ); ?>"
        <?php if ( $required ) echo "required"; ?> >

        <?php if ( $include_none ): ?>
          <?php echo $this->build_option( __( "None", "woocommerce-integration-nwsi" ), "" ); ?>
        <?php endif; ?>

        <?php foreach( $options as $option ): ?>
          <?php
          if ( array_key_exists( "active", $option ) && !$option["active"] ) {
            continue;
          }
          if ( array_key_exists( "value", $option ) && !array_key_exists( "name", $option ) ) {
            $option["name"] = $option["value"];
          }
          ?>
          <?php $data = isset( $option["data"] ) ? $option["data"] : array(); ?>
          <?php echo $this->build_option( $option["label"], $option["name"], $selected == $option["name"], $data ); ?>
        <?php endforeach; ?>
      </select>
      <?php
    }

    /**
     * Display an option HTML element as a string.
     *
     * @param  string  $label
     * @param  string  $value
     * @param  boolean $is_selected
     * @param  array   $data        Array which holds data-{key}="value" entries.
     * @return string
     */
    protected function build_option( $label, $value, $is_selected = false, $data = array() ) {
      ?>
      <option value="<?php esc_attr_e( $value ); ?>"
        <?php foreach( $data as $name => $value ): ?>
          data-<?php esc_attr_e( $name ); ?>="<?php esc_attr_e( $value ); ?>"
        <?php endforeach; ?>
        <?php if ( $is_selected ) echo " selected"; ?>>
        <?php esc_html_e( $label ); ?>
      </option>
      <?php
    }

    /**
     * Display input HTML element as a string.
     *
     * @param  string $name
     * @param  string $value
     * @param  string $type
     * @return void
     */
    protected function build_input( $name, $value, $type = "hidden" ) {
      ?>
      <input
        type="<?php esc_attr_e( $type ); ?>"
        name="<?php esc_attr_e( $name ); ?>"
        value="<?php esc_attr_e( $value ); ?>" />
      <?php
    }
  }
}
