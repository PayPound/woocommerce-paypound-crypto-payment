<?php

class WC_Paypoundcrypto_Payment_Gateway extends WC_Payment_Gateway{

    private $order_status;

	public function __construct(){
		$this->id = 'paypoundcrypto_payment';
		$this->method_title = __('Paypound Crypto Payment','woocommerce-Paypoundcrypto-payment-gateway');
		$this->method_description = __('Paypound Crypto Payment getway provide direct payment','woocommerce-Paypoundcrypto-payment-gateway');
		$this->title = __('Paypound Crypto Payment','woocommerce-Paypoundcrypto-payment-gateway');
		$this->has_fields = true;
		$this->init_form_fields();
		$this->init_settings();
		$this->enabled = $this->get_option('enabled');
		$this->title = $this->get_option('title');
		$this->description = $this->get_option('description');
		
		$this->order_status = $this->get_option('order_status');


		add_action('woocommerce_update_options_payment_gateways_'.$this->id, array($this, 'process_admin_options'));
	}

	public function init_form_fields(){
				$this->form_fields = array(
					'enabled' => array(
					'title' 		=> __( 'Enable/Disable', 'woocommerce-paypoundcrypto-payment-gateway' ),
					'type' 			=> 'checkbox',
					'label' 		=> __( 'Enable Paypound Crypto Payment', 'woocommerce-paypoundcrypto-payment-gateway' ),
					'default' 		=> 'no'
					),

		            'title' => array(
						'title' 		=> __( 'Method Title', 'woocommerce-paypoundcrypto-payment-gateway' ),
						'type' 			=> 'text',
						'description' 	=> __( 'This controls the title', 'woocommerce-paypoundcrypto-payment-gateway' ),
						'default'		=> __( 'Custom Payment', 'woocommerce-paypoundcrypto-payment-gateway' ),
						'desc_tip'		=> true,
					),
					'description' => array(
						'title' => __( 'Customer Message', 'woocommerce-paypoundcrypto-payment-gateway' ),
						'type' => 'textarea',
						'css' => 'width:500px;',
						'default' => 'None of the other payment options are suitable for you? please drop us a note about your favourable payment option and we will contact you as soon as possible.',
						'description' 	=> __( 'The message which you want it to appear to the customer in the checkout page.', 'woocommerce-paypoundcrypto-payment-gateway' ),
					),
					'testmode' => array(
						'title' 		=> __( 'TestMode', 'woocommerce-paypound-payment-gateway' ),
						'type' 			=> 'checkbox',
						'label' 		=> __( 'TestMode Enable for test Paypound Payment', 'woocommerce-paypound-payment-gateway' ),
						'default' 		=> 'yes'
					),
					'api_key' => array(
						'title' 		=> __( 'API Key', 'woocommerce-paypoundcrypto-payment-gateway' ),
						'type' 			=> 'text',
						'description' 	=> __( 'Api key', 'woocommerce-paypoundcrypto-payment-gateway' ),
						'default'		=> __( 'Api Key', 'woocommerce-paypoundcrypto-payment-gateway' ),
						'desc_tip'		=> true,
					),
					'order_status' => array(
						'title' => __( 'Order Status After The Checkout', 'woocommerce-paypoundcrypto-payment-gateway' ),
						'type' => 'select',
						'options' => wc_get_order_statuses(),
						'default' => 'wc-on-hold',
						'description' 	=> __( 'The default order status if this gateway used in payment.', 'woocommerce-paypoundcrypto-payment-gateway' ),
					),
			 );
	}
	

	
	

	public function process_payment( $order_id ) {
		global $woocommerce;
		$order = new WC_Order( $order_id );
		$user_id = get_post_meta( $order_id, '_customer_user', true );

		// Get an instance of the WC_Customer Object from the user ID
		$customer = new WC_Customer( $user_id );
		$amount =  (float) $order->get_total();;
				// Get account email
		$currency = get_woocommerce_currency();
		
		
		$ip_address=file_get_contents('http://checkip.dyndns.com/');
		
		$ip = str_replace("Current IP Address: ","",$ip_address);
		
		//$apikey = $this->woocommerce_paypoundcrypto_payment_api_key;
		$apikey = $this->settings['api_key'];
		//print_r($_POST['card']);exit;
		$args = array(
			'api_key' => $apikey,
			'first_name' => $_POST['billing_first_name'],
			'last_name' => $_POST['billing_last_name'],
			'address' => $_POST['billing_address_1'].''.$_POST['billing_address_2'],
			'country' => $_POST['billing_country'],
			'state' => $_POST['billing_state'],
			'city' => $_POST['billing_city'],
			'zip' => $_POST['billing_postcode'],
			'ip_address' => $ip,
			'email' => $_POST['billing_email'],
			'phone_no' => $_POST['billing_phone'],
			'amount' => sprintf('%0.2f', $amount),
			'currency' => $currency,
			'customer_order_id' => $order_id,
			'response_url' => $this->get_return_url( $order ),
		);
		$mode = $this->settings['testmode'];
		if($mode == 'yes'){
			$url = 'https://portal.paypound.ltd/api/test-crypto-transaction';
		}else if($mode == 'no'){
			$url = 'https://portal.paypound.ltd/api/crypto-transaction';
		}
		//print_r($args);exit;
		$curl = curl_init();
		$postData = json_encode($args);
		curl_setopt_array($curl, array(
		  CURLOPT_URL => $url,
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => '',
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 0,
		  CURLOPT_FOLLOWLOCATION => true,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => 'POST',
		  CURLOPT_POSTFIELDS =>$postData,
		  CURLOPT_HTTPHEADER => array(
			'Content-Type: application/json'
		  ),
		));

		$response = curl_exec($curl);
		//print_r($response);exit;
		curl_close($curl);
		$result = json_decode($response, true);
		//print_r($result);
		if(isset($result['status']) && $result['status'] == 'success'){
		// Mark as on-hold (we're awaiting the cheque)
		$order->update_status($this->order_status, __( 'Awaiting payment', 'woocommerce-paypoundcrypto-payment-gateway' ));
		// Reduce stock levels
		wc_reduce_stock_levels( $order_id );
		
		$order->add_order_note(esc_html('payment_order_id : '.$result['data']['order_id']),1);
		
		// Remove cart
		$woocommerce->cart->empty_cart();
		// Return thankyou redirect
		return array(
			'result' => 'success',
			'order_no' => $result['data']['order_id'],
			'redirect' => $this->get_return_url( $order )
		);
		}else if(isset($result['status']) && $result['status'] == '3d_redirect'){
			wc_reduce_stock_levels( $order_id );
			$order->update_status($this->order_status, __( 'Awaiting payment', 'woocommerce-paypoundcrypto-payment-gateway' ));
			$order->add_order_note(esc_html('Order goes to the 3ds redirect : '.$result['redirect_3ds_url']),1);
		
		// Remove cart
		$woocommerce->cart->empty_cart();
			
			return array(
			'result' => 'success',
			'redirect' => $result['redirect_3ds_url']
		);
			
		}else{
			wc_add_notice( __($result['message'],'woocommerce-paypoundcrypto-payment-gateway'), 'error');
			return false;
		}
	}

	
}
