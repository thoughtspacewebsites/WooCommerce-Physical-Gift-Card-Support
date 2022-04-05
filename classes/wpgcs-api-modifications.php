<?php
// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

/**
 * class WPGCS_API_Modifications
 *  
 * This class is responsible for any modifications that are necessary to the built
 * in WP Rest API. This includes modifying existing endpoints, adding new endpoints, etc...
**/

    
class WPGCS_API_Modifications {
		
    private static $active_instance = false;


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
    

    //NO DIRECT INNSTANTIATING PLZ
    private function __construct($dependencies) {
        $this->dependencies = $dependencies;
        
        $this->plugin_api_namespace = 'wpgcs/v1';
        
        $this->gateway = false;


        $this->routes_to_register = array(
            
            //Gift Cards
            array(
                'route' => '/gift-cards/?',
                'args' => array(
                    'methods' => 'GET',
                    'callback' => function(WP_REST_Request $request){
                        return call_user_func_array(array($this->dependencies['gift_cards'], 'REST_get_cards'), array($request));
                    },
                    'permission_callback' => function(){
                        return current_user_can('administrator') || current_user_can('employee') || current_user_can('retail_employee') || current_user_can('oakmont_manager');
                    }
                )
            ),
            array(
                'route' => '/gift-cards/?(?P<id>\d*)/?',
                'args' => array(
                    'methods' => 'GET',
                    'callback' => function(WP_REST_Request $request){
                        return call_user_func_array(array($this->dependencies['gift_cards'], 'REST_get_card_info'), array($request));
                    },
                    'permission_callback' => function(){
                        return current_user_can('administrator') || current_user_can('employee') || current_user_can('retail_employee') || current_user_can('oakmont_manager');
                    }
                )
            ),
            array(
                'route' => '/gift-cards/?(?P<id>\d*)/?',
                'args' => array(
                    'methods' => 'POST',
                    'callback' => function(WP_REST_Request $request){
                        return call_user_func_array(array($this->dependencies['gift_cards'], 'REST_update_card'), array($request));
                    },
                    'permission_callback' => function(){
                        return current_user_can('employee') || current_user_can('retail_employee') || current_user_can('administrator') || current_user_can('oakmont_manager');
                    }
                )
            ),
            array(
                'route' => '/gift-cards/?(?P<id>\d*)/?',
                'args' => array(
                    'methods' => 'DELETE',
                    'callback' => function(WP_REST_Request $request){
                        return call_user_func_array(array($this->dependencies['gift_cards'], 'REST_delete_card'), array($request));
                    },
                    'permission_callback' => function(){
                        return current_user_can('administrator') || current_user_can('oakmont_manager');
                    }
                )
            ),

            
         
        );
        
        
        add_action( 'rest_api_init', array(&$this, 'register_routes'));
        
    
    } 
    
    
    
    /**
     * public function register_routes()
     * 
     * Responsible for looping over $this->routes_to_register and
     * registering them within our WP API. More or less just a setup function
    **/
    public function register_routes(){
        foreach($this->routes_to_register as $route){
            register_rest_route( $this->plugin_api_namespace, $route['route'], $route['args']);
        }
    }

  

} 