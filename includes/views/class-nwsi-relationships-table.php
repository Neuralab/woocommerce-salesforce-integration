<?php
if ( !defined( "ABSPATH" ) ) {
  exit;
}

if( !class_exists( "WP_List_Table" ) ) {
  require_once( ABSPATH . "wp-admin/includes/class-wp-list-table.php" );
}

if ( !class_exists( "NWSI_Relationships_Table" ) ) {
  class NWSI_Relationships_Table extends WP_List_Table {

    public $bulk;

    /**
    * Class constructor
    */
    public function __construct() {
      parent::__construct();
      $this->_args["plural"] = "nwsi_relationships_table";

      $this->process_bulk_action();
    }

    /**
     * Set items array
     * @param array $items
     */
    public function set_items( $items ) {
      $this->items = $items;
    }

    /**
     * Prepare items for the table to process
     * @override
     */
    public function prepare_items() {
      $columns = $this->get_columns();
      $hidden = $this->get_hidden_columns();
      $sortable = $this->get_sortable_columns();

      $this->_column_headers = array( $columns, $hidden, $sortable );
    }

    /**
  	 * Generate the table navigation above or below the table
  	 * @param string $which
  	 */
  	protected function display_tablenav( $which ) {
  		?>
      	<div class="tablenav <?php echo esc_attr( $which ); ?>">

      		<?php if ( $this->has_items() ): ?>
      		<div class="alignleft actions bulkactions">
      			<?php $this->bulk_actions( $which ); ?>
      		</div>
      		<?php endif;
      		$this->extra_tablenav( $which );
      		$this->pagination( $which );
          ?>

      		<br class="clear" />
      	</div>
      <?php
    }

    /**
     * Define bulk actions
     * @override
     * @return array
     */
    public function get_bulk_actions() {
      return array(
        "delete"     => __( "Delete", "woocommerce-integration-nwsi" ),
        "activate"   => __( "Activate", "woocommerce-integration-nwsi" ),
        "deactivate" => __( "Deactivate", "woocommerce-integration-nwsi" ),
      );
    }

    /**
     * Process bulk choosen bulk action
     * @override
     */
    public function process_bulk_action() {

      if ( !array_key_exists( "bulk", $_POST ) || empty( $_POST["bulk"] ) ) {
        return;
      }

      // security check!
      if( isset( $_POST["_wpnonce"] ) && !empty( $_POST["_wpnonce"] ) ) {
        $nonce = filter_input( INPUT_POST, "_wpnonce", FILTER_SANITIZE_STRING );
        if ( !wp_verify_nonce( $nonce, "woocommerce-settings" ) ) {
          return;
        }
      }

      require_once ( plugin_dir_path( __FILE__ ) . "../controllers/core/class-nwsi-db.php" );
      $db = new NWSI_DB();

      $bulk = $_POST["bulk"];
      $action = $this->current_action();

      switch( $action ) {
        case "delete":
          $db->delete_relationships_by_id( $bulk );
          break;
        case "activate":
          $db->activate_relationships_by_id( $bulk );
          break;
        case "deactivate":
          $db->deactivate_relationships_by_id( $bulk );
          break;
        default:
          break;
      }
      return;
    }

    /**
     * Return HTML for row checkbox
     * @override
     * @param array $item
     * @return string
     */
    protected function column_cb( $item ) {
      return sprintf(
        '<input type="checkbox" name="bulk[]" value="%s" />', $item["id"]
      );
    }

    /**
     * Define columns to use in listing table
     * @override
     * @return array
     */
    public function get_columns() {
      $columns = array(
        "cb"           => "<input type ='checkbox' />",
        "id"           => __( "ID", "woocommerce-integration-nwsi" ),
        "relationship" => __( "Relationship (SF - WC)", "woocommerce-integration-nwsi" ),
        "date-created" => __( "Date Created", "woocommerce-integration-nwsi" ),
        "date-updated" => __( "Date Updated", "woocommerce-integration-nwsi" ),
        "active"       => __( "Is Active", "woocommerce-integration-nwsi" ),
      );

      return $columns;
    }

    /**
     * Define what data to show on each column of the table
     * @override
     * @param array   $item
     * @param string  $column_name - current column name
     * @return mixed
     */
    public function column_default( $item, $column_name ) {
      switch( $column_name ) {
        case "id":
        case "relationship":
        case "date-created":
        case "date-updated":
        case "active":
          return $item[ $column_name ];
        default:
          return print_r( $item, true ) ;
      }
    }

    /**
     * Define which columns are hidden
     * @override
     * @return array
     */
    public function get_hidden_columns() {
      return array( "id" );
    }

    /**
     * Define the sortable columns
     * @override
     * @return array
     */
    public function get_sortable_columns() {
      return array();
    }

  }
}
