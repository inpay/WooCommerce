<?php
/*
Plugin Name: InPay Woocommerce
Plugin URI: http://www.inpay.pl
Description: This plugin adds the InPay payment gateway to your Woocommerce plugin.  Woocommerce is required.
Version: 1.0
Author: Robertas Dereskevicius
Author URI: http://www.softdb.eu
License:

 * The MIT License (MIT)
 * 
 * Copyright (c) 2011-2014 InPay
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
*/


if (!function_exists('nn_active_nw_plugins')) {
  function nn_active_nw_plugins() {

        if (!is_multisite())
            return false;

        $nn_activePlugins = (get_site_option('active_sitewide_plugins')) ? array_keys(get_site_option('active_sitewide_plugins')) : array();
        return $nn_activePlugins;

  }
}


/**
 * Check if WooCommerce is active
 **/
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) || in_array('woocommerce/woocommerce.php', (array) nn_active_nw_plugins()) ) 
{
	function iplog($contents)
	{
		error_log($contents);
	}

	function declareWooInpay() 
	{
		if ( ! class_exists( 'WC_Payment_Gateways' ) ) 
			return;

		class WC_Inpay extends WC_Payment_Gateway 
		{
		
			public function __construct() 
			{
				$this->id = 'inpay';
				$this->icon = plugin_dir_url(__FILE__).'inpay.png';
				$this->has_fields = false;
			 
				// Load the form fields.
				$this->init_form_fields();
			 
				// Load the settings.
				$this->init_settings();
			 
				// Define user set variables
				$this->title = $this->settings['title'];
				$this->description = $this->settings['description'];
			 
				// Actions
				add_action('woocommerce_update_options_payment_gateways_'.$this->id, array(&$this, 'process_admin_options'));
				//add_action('woocommerce_thankyou_cheque', array(&$this, 'thankyou_page'));
			 
				// Customer Emails
				add_action('woocommerce_email_before_order_table', array(&$this, 'email_instructions'), 10, 2);
			}
			
			function init_form_fields() 
			{
				$this->form_fields = array(
					'enabled' => array(
						'title' => __( 'Enable/Disable', 'woothemes' ),
						'type' => 'checkbox',
						'label' => __( 'Enable Inpay Payment', 'woothemes' ),
						'default' => 'yes'
					),
					'title' => array(
						'title' => __( 'Title', 'woothemes' ),
						'type' => 'text',
						'description' => __( 'This controls the title which the user sees during checkout.', 'woothemes' ),
						'default' => __( 'Bitcoins', 'woothemes' )
					),
					'description' => array(
						'title' => __( 'Customer Message', 'woothemes' ),
						'type' => 'textarea',
						'description' => __( 'Message to explain how the customer will be paying for the purchase.', 'woothemes' ),
						'default' => 'You will be redirected to InPay.pl to complete your purchase.'
					),
					'apiKey' => array(
						'title' => __('API Key', 'woothemes'),
						'type' => 'text',
						'description' => __('Enter the API key you created at InPay.pl'),
					),
					'secretApiKey' => array(
						'title' => __('API Key secret', 'woothemes'),
						'type' => 'text',
						'description' => __('Enter the API key you created at InPay.pl'),
					),
					'minConfirmations' => array(
						'title' => __('minimum Confirmations', 'woothemes'),
						'type' => 'text',
						'description' => __('minimum required confirmations from Bitcoin network, required to update status to confirmed. default and minimum value is: 6 '),
						'default' => 6
					),
					'fbaEnabled' => array(
						'title' => __('Fullfullment By Amazon Enabled', 'woothemes'),
						'type' => 'checkbox',
						'description' => 'FBA account requred.  Fill in account info at ./fba_options.php.',
						'default' => 'no',
					), 
				);
			}
				
			public function admin_options() {
				?>
				<h3><?php _e('Bitcoin Payment', 'woothemes'); ?></h3>
				<p><?php _e('Allows bitcoin payments via InPay.pl.', 'woothemes'); ?></p>
				<table class="form-table">
				<?php
					// Generate the HTML For the settings form.
					$this->generate_settings_html();
				?>
				</table>
				<?php
			} // End admin_options()
			
			public function email_instructions( $order, $sent_to_admin ) {
				return;
			}

			function payment_fields() {
				if ($this->description) echo wpautop(wptexturize($this->description));
			}
			 
			function thankyou_page() {
				if ($this->description) echo wpautop(wptexturize($this->description));
			}

			function process_payment( $order_id ) {
				require 'bp_lib.php';
				
				global $woocommerce, $wpdb;

				$order = new WC_Order( $order_id );

				// Mark as on-hold (we're awaiting the coins)
				$order->update_status('on-hold', __('Awaiting payment notification from InPay.pl', 'woothemes'));
				
				// invoice options
				$vcheck = explode('.',WC_VERSION);
                                if(trim($vcheck[0]) >= '2' && trim($vcheck[1]) >= '1')
                                    $thanks_link = $this->get_return_url($this->order);
                                else
                                    $thanks_link =  get_permalink(get_option('woocommerce_thanks_page_id'));

				$redirect = add_query_arg('key', $order->order_key, add_query_arg('order', $order_id, $thanks_link));
				
				$notificationURL = get_option('siteurl')."/?inpay_callback=1";
				
				$currency = get_woocommerce_currency();
				//$currency = 'PLN';
				
				
				$prefix = 'billing_';
				$options = array(
					'apiKey' => $this->settings['apiKey'],
					'minConfirmations' => $this->settings['minConfirmations'],
					'currency' => $currency,
					'successUrl' => $redirect,
					'failUrl' => $redirect,
					'callbackUrl' => $notificationURL,
					'customerName' => $order->{$prefix.first_name}.' '.$order->{$prefix.last_name},
					'customerAddress1' => $order->{$prefix.address_1},
					'customerAddress2' => $order->{$prefix.address_2},
					'customerCity' => $order->{$prefix.city},
					'customerState' => $order->{$prefix.state},
					'customerZip' => $order->{$prefix.postcode},
					'customerCountry' => $order->{$prefix.country},
					'customerEmail' => $order->billing_email,
					'customerPhone' => $order->billing_phone,
					);
					
				if (strlen($order->{$prefix.company}))
					$options['customerName'] = $order->{$prefix.company}.' c/o '.$options['customerName'];
				
				foreach(array('customerName', 'customerAddress1', 'customerAddress2', 'customerCity', 'customerState', 'customerZip', 'customerCountry', 'customerPhone', 'customerEmail') as $trunc)
					$options[$trunc] = substr($options[$trunc], 0, 100); // api specifies max 100-char len

				$invoice = bpCreateInvoice($order_id, $order->order_total, $order_id, $options );
				if (isset($invoice['messageType']) && $invoice['messageType']!='success')
				{
					$order->add_order_note(var_export($invoice['message'], true));
					$woocommerce->add_error(__('Error creating InPay invoice.  Please try again or try another payment method.'));
				}
				else
				{
					$woocommerce->cart->empty_cart();
				
					return array(
						'result'    => 'success',
						'redirect'  => $invoice['redirectUrl'],
					);
				}			 
			}
		}
	}

	include plugin_dir_path(__FILE__).'callback.php';

	function add_inpay_gateway( $methods ) {
		$methods[] = 'WC_Inpay'; 
		return $methods;
	}
	
	add_filter('woocommerce_payment_gateways', 'add_inpay_gateway' );

	add_action('plugins_loaded', 'declareWooInpay', 0);
	
	
}
