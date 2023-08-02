<?php
// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

class WPGCS_Gateway_Gift_Card extends WC_Payment_Gateway {


	public function __construct() {
  
		$this->id = 'gift_card';
		$this->icon = apply_filters('wpgcs_gift_card_icon', '');
		$this->has_fields = true;
		$this->method_title = __( 'Gift Card', 'wpgcs' );
		$this->method_description = __( 'Gift card payment (on site and in store)', 'wpgcs' );
	  
		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();
	  
		// Define user set variables
		$this->title = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->instructions = $this->get_option( 'instructions', $this->description );
		
		// Actions
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
	  
		// Customer Emails
		add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
	}



	public function init_form_fields() {
  
		$this->form_fields = apply_filters( 'wpgcs_gift_card_form_fields', array(
	  
			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'wpgcs' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Cash Payment', 'wpgcs' ),
				'default' => 'yes'
			),
			
			'title' => array(
				'title'       => __( 'Gateway Title', 'wpgcs' ),
				'type'        => 'text',
				'description' => __( 'This controls the title for the payment method.', 'wpgcs' ),
				'default'     => __( 'Cash In Store', 'wpgcs' ),
				'desc_tip'    => true,
			),
			
			'description' => array(
				'title'       => __( 'Description', 'wpgcs' ),
				'type'        => 'textarea',
				'description' => __( 'The description of the payment method ', 'wpgcs' ),
				'default'     => __( 'Pay with cash in store.', 'wpgcs' ),
				'desc_tip'    => true,
			),
			
			'instructions' => array(
				'title'       => __( 'Additional Instructions', 'wpgcs' ),
				'type'        => 'textarea',
				'description' => __( 'Instructions that will be added to the thank you page and confirmation emails.', 'oakmont' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			
		));
	}


	/**
     * Check if the gateway is available for use.
	 * Shut it down on the site, this is an API only gateway
     *
     * @return bool
     */
    public function is_available() {
    	
        //uncomment below to force disable on site
        return false;

       //return true;
    }
    
    
    public function payment_fields() {
		// ok, let's display some description before the payment form
		if ( $this->description ) {
			// display the description with <p> tags etc.
			echo wpautop( wp_kses_post( $this->description ) );
		}
		echo "Pay with a gift card";
	}

    
    /**
     * Validate frontend fields.
     *
     * Validate payment fields on the frontend.
	 * Useless method
     *
     * @return bool
     */
    public function validate_fields() {

        //Bypassing for now
		return true;

        //Check the gift card balance to make sure there's enough on it
    }


	/**
	 * Output for the order received page.
	 */
	public function thankyou_page() {
		if ( $this->instructions ) {
			echo wpautop( wptexturize( $this->instructions ) );
		}
	}


	/**
	 * Add content to the WC emails.
	 *
	 * @access public
	 * @param WC_Order $order
	 * @param bool $sent_to_admin
	 * @param bool $plain_text
	 */
	public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		if ( $this->instructions && ! $sent_to_admin && $this->id === $order->get_payment_method() && $order->has_status( 'processing' ) ) {
			echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
		}
	}


	/**
	 * Process the payment and return the result
	 *
	 * @param int $order_id
	 * @return array
	 */
	public function process_payment( $order_id ) {

		$order = wc_get_order( $order_id );
		$user = $order->get_user();
		
		
		
		
		if(!$_POST['payment_amount']){
			$error_message = __( 'No amount tendered!', 'wpgcs' );
			$order->update_status( 'failed', $error_message );
			wc_add_notice($error_message, 'error');
			return array(
				'result' => 'failure',
				'redirect' => ''
			);
		}
	
	
		$order_total = floatval($order->get_total());
		$amount_already_paid = get_field('amount_received', $order_id);
		$new_amount_paid = floatval($amount_already_paid) + floatval($_POST['payment_amount']);
		$new_amount_paid = number_format($new_amount_paid, 2, '.', '');
		
		// setlocale(LC_MONETARY, 'en_US.UTF-8');
		// $formatted_amount_paid = money_format('%.2n', floatval($_POST['payment_amount']));

		//Trying new way to format currency
		$fmt = new NumberFormatter( 'en_US', NumberFormatter::CURRENCY );
		$change_due = $fmt->formatCurrency( floatval($_POST['payment_amount']), 'USD');

		
		
		$paid_in_full = false;
		$change_due = false;
		if($new_amount_paid < $order_total){
			//Order still not paid, leave as on hold
			$order->add_order_note( "Partial gift card payment received for order: ".$formatted_amount_paid, false, true );	
			$order->update_status( 'pending', __( 'Still awaiting full payment', 'wpgcs' ) );
		}
		else{
			$paid_in_full = true;
			// Mark as processing
			$order->update_status( 'processing', __( 'Full gift card payment received.', 'oakmont' ) );
			// Reduce stock levels
			$order->reduce_order_stock();
			$order->set_date_paid(date(DateTime::ISO8601));
			$order->payment_complete();
			$order->save();
		}
		
		//Make sure that gift card is listed as a payment method for this order
		$existing_payment_method = $order->get_payment_method();
		if(!$existing_payment_method){
			$payment_method = 'gift_card';
		}
		else{
			$methods = explode(', ', $existing_payment_method);
			if(!in_array('gift_card', $methods)){
				array_push($methods, 'gift_card');
			}
			$payment_method = implode(', ', $methods);
		}
		
		$existing_payment_method_title = $order->get_payment_method_title();
		if(!$existing_payment_method_title){
			$payment_method_title = 'Gift Card';
		}
		else{
			$methods = explode(', ', $existing_payment_method_title);
			if(!in_array('Gift Card', $methods)){
				array_push($methods, 'Gift Card');
			}
			$payment_method_title = implode(', ', $methods);
		}
		
		$order->set_payment_method($payment_method);
		$order->set_payment_method_title($payment_method_title);
		$order->save();
	
	
        if(function_exists('update_field')){
		    update_field('amount_received', $new_amount_paid, $order_id);
        }
		
		// Remove cart
		if(isset(WC()->cart)){
			WC()->cart->empty_cart();
		}
		return array(
			'result' 	=> 'success',
			'redirect'	=> $this->get_return_url( $order ),
			'meta' => array(
				'paid_in_full' => $paid_in_full,
				'change_due' => $change_due
			)
		);
	
	}

}

?>