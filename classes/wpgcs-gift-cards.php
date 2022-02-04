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


    public function add_balance($card_number, $amount){
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpgcs_gift_cards';
        //First find the current balance
        $current_balance = $wpdb->get_var( "SELECT amount FROM $table_name WHERE gift_card_number = '$card_number'" );
        $existing_amount_loaded = $wpdb->get_var( "SELECT total_amount_loaded FROM $table_name WHERE gift_card_number = '$card_number'" );
        
        $new_balance = floatval($amount);
        if($current_balance){
            $amount_loaded = $new_balance;
            $new_balance += floatval($current_balance);
            return $wpdb->update( $table_name, array( 
                'amount' => $new_balance,
                'last_reload_date' => date('Y-m-d H:i:s'),
                'total_amount_loaded' => floatval($existing_amount_loaded) + $amount_loaded
             ), array( 'gift_card_number' => $card_number) );
        }
        else{
            return $wpdb->insert( $table_name, array( 
                'gift_card_number' => $card_number, 
                'amount' => $new_balance ,
                'total_amount_loaded' => $new_balance,
                'activation_date' => date('Y-m-d H:i:s'),
                'last_reload_date' => date('Y-m-d H:i:s')
            ) );
        }
        
    }

    public function REST_get_cards(WP_REST_Request $request){
        global $wpdb;
        $params = $request->get_params();

        $page = $params['page'] ? $params['page'] - 1 : 0;
        $per_page = $params['per_page'] ? $params['per_page'] : 50;

        $filters = $params['filters'] ? json_decode($params['filters']) : array();
 
        $query = 'SELECT SQL_CALC_FOUND_ROWS * from '.$wpdb->prefix.'wpgcs_gift_cards where 1=1';
        
        if($filters->number){
            $query .= ' AND gift_card_number LIKE "%'.$filters->number.'%"';
        }

        if($filters->balance){
            $query .= ' AND amount LIKE "%'.$filters->balance.'%"';
        }

        if($filters->activation_date){
            $start_date = date('Y-m-d', strtotime($filters->activation_date));
            $end_date = date('Y-m-d', strtotime($filters->activation_date));
            $start_date .= ' 00:00:00';
            $end_date .= ' 23:59:59';

            $query .= '
                HAVING (activation_date between "'.$start_date.'" and "'.$end_date.'") 
            ';      
        }
        
        $query .= ' ORDER BY last_reload_date DESC LIMIT '.$per_page.' OFFSET '.$page*$per_page;

        $cards = $wpdb->get_results($query);
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
                'total_amount_loaded' => $params['amount'],
                'activation_date' => date('Y-m-d H:i:s'),
                'last_reload_date' => date('Y-m-d H:i:s')
            ));
        }
        else{
            //We found a matching card, so update instead...
            $total_amount_loaded = $params['amount'] - $existing_cards[0]->amount;
            $card_result = $wpdb->update($wpdb->prefix.'wpgcs_gift_cards', array(
                'amount' => $params['amount'],
                'total_amount_loaded' => $existing_cards[0]->total_amount_loaded ? $total_amount_loaded + $existing_cards[0]->total_amount_loaded : $params['amount'],
                'last_reload_date' => date('Y-m-d H:i:s')
            ), array(
                'gift_card_number' => $params['id']
            ));
        }

        return $card_result === false ? false : true;
        
    }


}