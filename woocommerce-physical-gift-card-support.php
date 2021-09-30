<?php
/*
 * Plugin Name: WooCommerce Physical Gift Card Support
 * Plugin URI: https://thoughtspacedesigns.com
 * Description: Take gift card payments in store. Adds support for purchasing gift cards to use on site, as well as for in store encoding.
 * Author: Thought Space Designs
 * Author URI: http://thoughtspacedesigns.com
 * Version: 1.0.0
 */


// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}


/**
 * class WPGCS_Manager
 * 
 * The main manager for our gift card support plugin
**/
class WPGCS_Manager {
    
    public static $plugin_path;
    public static $plugin_url;
    
    private static $initialized = false;
    public static $products_to_create = array(

    );
    
    /**
     * Set up a static variable to hold our dependencies
    **/
    public static $dependencies = array();
    
    
    
    /**
     * private function __construct()
     * 
     * DON'T ALLOW CALLING __construct DIRECTLY
     * Class must be instantiated via the static initialize method below.
    **/
    private function __construct(){}
        
    public static function initialize(){
        
        if (self::$initialized){
            return;
        }
        self::$initialized = true;
        
        /**
         * Set our plugin path and plugin url variables. These have a trailing slash
        **/
        self::$plugin_path = plugin_dir_path(__FILE__);
        self::$plugin_url = plugin_dir_url(__FILE__);
        
        
        /**
         * Set up a static variable to hold our dependencies
        **/
        self::$dependencies = array();


        self::$products_to_create = array(
            array(
                'post_info' => array(
                    'post_title' => 'Gift Card',
                    'post_name' => 'gift-card',
                    'post_status' => 'Publish',
                    'post_type' => 'product'
                ),
                'meta' => array(
                    
                )
            ),
            
        );
        
        
        
        //Load in our plugin support files and instantiate all of the base classes
       	self::load_classes();
       	self::instantiate_classes();

        //Any functions that should be run when the plugin is first activated
        register_activation_hook( __FILE__, __CLASS__.'::on_plugin_activation');

       	
        
    }
    
    
    /**
     * private static function load_classes()
     * 
     * This function loads any files that are used to build our plugin.
     * These are files defined in the "core" folder.
    **/
    private static function load_classes(){

        //Load up our core plugin files   
            //Helper classes
            require_once(self::$plugin_path."classes/wpgcs-api-modifications.php");
            require_once(self::$plugin_path."classes/wpgcs-database-loader.php");
            require_once(self::$plugin_path."classes/wpgcs-gift-cards.php");
    }
    
    
    private static function instantiate_classes(){
        self::$dependencies['gift_cards'] = WPGCS_Gift_Cards::initialize();
        WPGCS_Database_Loader::initialize();
        WPGCS_API_Modifications::initialize(array(
            'gift_cards' => self::$dependencies['gift_cards']
        ));
    }


    private static function create_products(){
        
        foreach(self::$products_to_create as $product) { 
            //Create the product
            $existing_page = get_page_by_path($product['post_info']['post_name'], OBJECT, 'product');
            if($existing_page == null){
                $post_id = wp_insert_post( $product['post_info']);
        
                // Then we use the product ID to set all the posts meta
                wp_set_object_terms( $post_id, 'simple', 'product_type' ); // set product is simple/variable/grouped
                update_post_meta( $post_id, '_visibility', 'visible' );
                update_post_meta( $post_id, '_stock_status', 'instock');
                update_post_meta( $post_id, 'total_sales', '0' );
                update_post_meta( $post_id, '_downloadable', 'no' );
                update_post_meta( $post_id, '_virtual', 'no' );
                update_post_meta( $post_id, '_regular_price', '' );
                update_post_meta( $post_id, '_sale_price', '' );
                update_post_meta( $post_id, '_purchase_note', '' );
                update_post_meta( $post_id, '_featured', 'no' );
                update_post_meta( $post_id, '_price', '0' );
            }
        }
    
    }

    /**
     * public static function on_plugin_activation()
     * 
     * This function is fired via 'register_activation_hook' in our initiliaze method
     * Runs on plugin activation
    **/
    public static function on_plugin_activation(){
        self::create_products();        
    }

    


}


WPGCS_Manager::initialize();
