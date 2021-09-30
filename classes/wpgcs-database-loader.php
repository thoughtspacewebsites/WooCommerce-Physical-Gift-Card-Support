<?php
// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}



/**
 * This class is responsible for setting up our database tables
 * for our plugin. It also keeps track of any changes that occur
 * to these tables across updates.
 **/
class WPGCS_Database_Loader {
	
	private static $active_instance = false;
	
	private $dependencies = array();
	
	private $tables_to_register = array();
    private $db_version;
    
	
	/**
     * public static function initialize($dependencies = array())
     * 
     * This is our singleton replacement for __construct()
     * We made __construct private so it can't be called from outside
     * our class, then we make everyone instantiate by calling the
     * initialize method.
    **/
    public static function initialize($dependencies = array()) {
        
        if( null == self::$active_instance ) {
            $classname = get_called_class();
            self::$active_instance = new $classname($dependencies);
        } 
        return self::$active_instance;
        
    } 
    

    //NO DIRECT INSTANTIATING PLZ
    private function __construct($dependencies){
        
        $this->dependencies = $dependencies;
        
        $this->db_version = "1.0";
        $this->additional_table_prefix = 'wpgcs_';
        
        
        global $wpdb;
        
        $this->tables_to_register = array(
            "gift_cards" => "
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                gift_card_number varchar(20),
                activation_date datetime,
                last_reload_date datetime,
                amount varchar(10),
                PRIMARY KEY  (id),
                KEY gcn (gift_card_number)
            "
            
        );
        
        $this->register_hooks();
        
        
    }
    
    



    /**
     * public function install_database_tables()
     * 
     * This function is responsible for installing our
     * initial database tables
    **/
    public function install_database_tables(){
     
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table_prefix = $wpdb->prefix;
        
        foreach($this->tables_to_register as $table_name => $create_statement){
            $sql = "
                CREATE TABLE ".$wpdb->prefix.$this->additional_table_prefix.$table_name." (
                ".$create_statement."
                ) ".$charset_collate.";
            ";
            dbDelta( $sql );
        }
        
        update_option("wpgcs_db_version", $this->db_version );
        
    }
    
    /**
     * public function check_database_update()
     * 
     * This function runs on every page load to check
     * and see if our database version has updated.
     * If it has, it reruns our install function with the new
     * code. In order to trigger this, simply increment the 
     * DB version number after updating the SQL in the
     * install_database_tables method
    **/
    public function check_database_update() {
        if ( get_site_option( 'wpgcs_db_version' ) != $this->db_version ) {
            $this->install_database_tables();
        }
    }
    
    
    
    /**
     * public function get_all_columns_for_table($table)
     * 
     * This function accepts a table name and retrieves all of the column names
     * that exist withinn it. It's a good helper for functions that need
     * to know which values exist in which tables for saving purposes, etc
    **/
    public function get_all_columns_for_table($table){
        global $wpdb;
        
        $all_columns = $wpdb->get_results("
            SELECT COLUMN_NAME 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA='".$wpdb->dbname."' 
                AND TABLE_NAME='".$wpdb->prefix.$table."';
        ");
        
        $all_columns = array_map(function($item){
            return $item->COLUMN_NAME;
        }, $all_columns);
        return $all_columns;
    }
    
    
    /**
     * public function register_hooks()
     * 
     * This function is responsible for registering the necessary
     * hooks to make our plugin install our database tables
    **/
    public function register_hooks(){
        register_activation_hook( WPGCS_Manager::$plugin_path.'woocommerce-physical-gift-card-support.php', array(&$this, 'install_database_tables') );
        add_action( 'plugins_loaded', array(&$this, 'check_database_update'));
    }



}
