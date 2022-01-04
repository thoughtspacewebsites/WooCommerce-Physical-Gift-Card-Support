<?php
// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}



/**
 * This class handles any mods to the inbuilt WooCommerce functionality
 * required to make our plugin function
 **/
class WPGCS_Woocommerce_Overrides {
	
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
        
        //When order is paid, we need to update the gift card balance
        add_action('woocommerce_order_status_processing', array(&$this, 'update_card_balance'), 10, 1);

    }
    
    

    public function update_card_balance($order_id){
        if( ! get_post_meta( $order_id, '_updated_gc_balance', true ) ) {
            //Start by finding all the products on this order and checking if one is a gift card
            $order = wc_get_order($order_id);
            $order_items = $order->get_items();
            foreach($order_items as $item){
                $product = $item->get_product();
                $slug = $product->get_slug();
                if($slug == "gift-card"){

                    //We found a gift card, lets get the card number from meta data and add this balance to our DB
                    $card_number = $item->get_meta('gift_card_code');

                    //Now we need to get the amount of the gift card
                    $amount = $item->get_total();
                    
                    //Finally we can add the amount to the gift card
                    $this->dependencies['gift_cards']->add_balance($card_number, $amount);
                    
                }
            }
            
            $order->update_meta_data( '_updated_gc_balance', true );
            $order->save();
        }
    }
}
