<?php

// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}


/**
 * Handles main gift card functionality
 **/
class WPGCS_Gift_Cards  {
	
	private static $active_instance = false;
	
	private $dependencies = array();
	
	
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
        
        
    }



    public function REST_get_cards(WP_REST_Request $request){
        global $wpdb;
        $params = $request->get_params();

        $page = $params['page'] ? $params['page'] - 1 : 0;
        $per_page = $params['per_page'] ? $params['per_page'] : 50;
 
        $cards = $wpdb->get_results('SELECT SQL_CALC_FOUND_ROWS * from '.$wpdb->prefix.'wpgcs_gift_cards ORDER BY last_reload_date DESC LIMIT '.$per_page.' OFFSET '.$page*$per_page);
        $found_rows = $wpdb->get_results('SELECT FOUND_ROWS() as count');
        
        $response = new WP_REST_Response($cards, 200);

        $response->header( 'X-WP-Total', $found_rows[0]->count ); // total = total number of post
        $response->header( 'X-WP-TotalPages', ceil($found_rows[0]->count/$per_page) ); // maximum number of pages

        return $response;

    }

    public function REST_get_card_info(WP_REST_Request $request){
        global $wpdb;
        $params = $request->get_params();
        if(!$params['id']){
            return new WP_Error('missing_params', 'Please specify a gift card id / number to look up');
        }
        $existing_cards  = $wpdb->get_results($wpdb->prepare('select * from '.$wpdb->prefix.'wpgcs_gift_cards where gift_card_number = %d', $params['id']));

        if(!$existing_cards || !count($existing_cards)){
            return false;
        }
        else{
            return $existing_cards[0];
        }
    }


    public function REST_update_card(WP_REST_Request $request){
        global $wpdb;
        $params = $request->get_params();

        if(!$params['id'] || !$params['amount']){
            return new WP_Error('missing_params', 'Please specify a gift card ID / number to activate or reload, and an amount to load');
        }

        if(!is_numeric($params['amount'])){
            return new WP_Error('wrong_format', 'Specified amount is not a number');
        }

        $existing_cards  = $wpdb->get_results($wpdb->prepare('select * from '.$wpdb->prefix.'wpgcs_gift_cards where gift_card_number = %d', $params['id']));

        $card_result = false;
        if(!$existing_cards || !count($existing_cards)){
            //Card doesn't exist yet, create a new one in the DB
            $card_result = $wpdb->insert($wpdb->prefix.'wpgcs_gift_cards', array(
                'gift_card_number' => $params['id'],
                'amount' => $params['amount'],
                'activation_date' => date('Y-m-d H:i:s'),
                'last_reload_date' => date('Y-m-d H:i:s')
            ));
        }
        else{
            //We found a matching card, so update instead...
            $card_result = $wpdb->update($wpdb->prefix.'wpgcs_gift_cards', array(
                'amount' => $params['amount'],
                'last_reload_date' => date('Y-m-d H:i:s')
            ), array(
                'gift_card_number' => $params['id']
            ));
        }

        return $card_result === false ? false : true;
        
    }


}