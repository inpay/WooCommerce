<?php

/**
 * The MIT License (MIT)
 * 
 * Copyright (c) 2014 Robertas Dereskevicius <info@softdb.eu>
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

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) 
{
	
	function inpay_callback()
	{
		if(isset($_GET['inpay_callback']))
		{
/*
$fh = fopen("debug.txt","a");
fwrite($fh,print_r($_SERVER,true));
fwrite($fh,print_r($_GET,true));
fwrite($fh,print_r($_POST,true));
fclose($fh);
*/
			global $woocommerce;
			
			require(plugin_dir_path(__FILE__).'bp_lib.php');
			
			$gateways = $woocommerce->payment_gateways->payment_gateways();
			if (!isset($gateways['inpay']))
			{
				error_log('inpay plugin not enabled in woocommerce');
				return;
			}
			$bp = $gateways['inpay'];
			$valid = bpVerifyNotification( $bp->settings['secretApiKey'] );

			if (!$valid)
				error_log($response);
			else
			{
				$orderId = $_POST["orderCode"];
				$order = new WC_Order( $orderId );

				switch($_POST['status'])
				{
					case 'new':
					case 'received':
						break;
					case 'confirmed':
						$order->add_order_note( __('InPay bitcoin payment confirmed. Awaiting network confirmation and completed status.', 'woothemes') );
					case 'paid':
						
						if ( in_array($order->status, array('on-hold', 'pending', 'failed' ) ) )
						{
							$order->payment_complete();		
							processOrderFBA($bp, $order);
							$order->add_order_note( __('InPay bitcoin payment completed. Payment credited to your merchant account.', 'woothemes') );
						}
						
						break;
					case 'expired':
					case 'aborted':
					case 'invalid':
						$order->add_order_note( __('Bitcoin payment is invalid for this order! The payment was not confirmed by the network within 1 hour.', 'woothemes') );
						$order->update_status('failed');
						break;
				}
			}
		}
	}
	
	function processOrderFBA($inpay, $order)
	{
		if (!$inpay->settings['fbaEnabled'])
			return;
			
		$orderInfo = 'order '.$order->id.':'; // for log

		require_once (plugin_dir_path(__FILE__).'FBAOutboundServiceMWS/config.inc.php');		
		
		$optionsFile = plugin_dir_path(__FILE__).'fba_options.php';
		if (!file_exists($optionsFile)) {
			error_log($orderInfo.'fba_options.php not found.  Copy fba_options.php.sample and fill in details.');
			return;
		}
		require_once ($optionsFile);
		
		// gather order info
		$items = $order->get_items();
		
		$orderItems = array();
		$hasSkus = false; // does this order have any skus?
		$hasBlanks = false; // does this order have any blank skus?
		foreach ($items as $i)
		{
			$product = get_product($i['product_id']);
			if (strlen($product->get_sku()))
				$hasSkus = true;
			else 
			{
				$hasBlanks = true;
				continue;
			}
			$orderItems[] = array(
				'currency' => get_woocommerce_currency(),
				'value' => $i['line_subtotal'],
				'sku' => $product->get_sku(),
				'quantity' => $i['qty']);
		}				
		if (!$hasSkus)
			return true; // nothing to do
		$prefix = ($order->shipping_address_1) ? 'shipping_' : 'billing_';
		$address = array(
			'name' => $order->{$prefix.first_name}.' '.$order->{$prefix.last_name},
			'line1' => $order->{$prefix.address_1},
			'line2' => $order->{$prefix.address_2},
			'line3' => '',
			'city' => $order->{$prefix.city},
			'state' => $order->{$prefix.state},
			'country' => $order->{$prefix.country},
			'zip' => $order->{$prefix.postcode},
			'phone' => $order->billing_phone); // there is no shipping_phone
		if (strlen($order->{$prefix.company}))
			$address['name'] = $order->{$prefix.company}.' c/o '.$address['name'];
				
		// find fba options by looking for country
		foreach($bpfbaOptions as $o) {
			if (!strlen($o['countries'])) { 
				$bpfba = $o; // blank country means "use this if above entries don't match"
				break;
			}
			$countries = array_map('trim', explode(',',$o['countries']));			
			if (in_array($address['country'], $countries) === TRUE) {
				$bpfba = $o;
				break;
			}
		}		
		if (!isset($bpfba)) {	
			error_log($orderInfo.'Destination address not found in fba_options.php');
			$order->update_status('failed');
			return false;
		}
		
		// apply fba options 
		$orderId = $order->id;
		$shippingSpeed = $bpfba['shippingSpeed'];
		$fulfillmentPolicy = $bpfba['fulfillmentPolicy'];
		$awsAccessKey = $bpfba['awsAccessKey'];
		$secretKey = $bpfba['secretAccessKey'];
		$merchantId = $bpfba['merchantId'];
		$marketplaceId = $bpfba['marketplaceId'];
		$endpointUrl = $bpfba['endpointUrl'];

		// create required FBA objects
		$config = array (
			'ServiceURL' => $endpointUrl,
			'ProxyHost' => null,
			'ProxyPort' => -1,
			'MaxErrorRetry' => 3
			);
		$service = new FBAOutboundServiceMWS_Client(
			$awsAccessKey, 
			$secretKey, 
			$config,
			APPLICATION_NAME,
			APPLICATION_VERSION);
		
		$items = array();
		$orderItemId=1;
		foreach($orderItems as $i)
		{
			$value = new FBAOutboundServiceMWS_Model_Currency();
			$value->setCurrencyCode($i['currency']);
			$value->setValue($i['value']);
			 
			$item = new FBAOutboundServiceMWS_Model_FulfillmentOrderItem();
			$item->setSellerSKU($i['sku']); // must be amazon's SKU
			$item->setSellerFulfillmentOrderItemId($orderItemId++); // seller can choose this
			$item->setQuantity( (int)$i['quantity'] ); // must be integer or FBA server fails
			//$item->setPerUnitDeclaredValue($value);
			$items[] = $item;
		}

		$list = new FBAOutboundServiceMWS_Model_FulfillmentOrderItemList();
		$list->setMember($items);

		$emails = new FBAOutboundServiceMWS_Model_NotificationEmailList();
		
		$fbaAddress = new FBAOutboundServiceMWS_Model_Address();
		$fbaAddress->setName($address['name']);
		$fbaAddress->setLine1($address['line1']);
		$fbaAddress->setLine2($address['line2']);
		$fbaAddress->setLine3($address['line3']);
		$fbaAddress->setCity($address['city']);
		$fbaAddress->setStateOrProvinceCode($address['state']);
		$fbaAddress->setCountryCode($address['country']);
		$fbaAddress->setPostalCode($address['zip']);
		$fbaAddress->setPhoneNumber($address['phone']);		

		$request = new FBAOutboundServiceMWS_Model_CreateFulfillmentOrderRequest();
		$request->setSellerId($merchantId);
		$request->setMarketplace($marketplaceId);
		$request->setSellerFulfillmentOrderId($orderId);
		$request->setDisplayableOrderId($orderId);
		$request->setDisplayableOrderDateTime(date('Y-m-d', time()));
		$request->setDisplayableOrderComment("Thank you for your order.");
		$request->setShippingSpeedCategory($shippingSpeed);
		$request->setDestinationAddress($fbaAddress);
		$request->setFulfillmentPolicy($fulfillmentPolicy);
		$request->setFulfillmentMethod('Consumer');
		$request->setNotificationEmailList($emails);
		$request->setItems($list);
		
		// send request to amazon
		try {
			$response = $service->createFulfillmentOrder($request);

			if ($response->isSetResponseMetadata()) { 
				$responseMetadata = $response->getResponseMetadata();
				if ($responseMetadata->isSetRequestId()) 
				{
					// success!
					if (!$hasBlanks)
						$order->update_status('completed');
				}
			} 

		} catch (FBAOutboundServiceMWS_Exception $ex) {
			$order->update_status('failed');
				
			error_log("Caught Exception: " . $ex->getMessage());
			error_log("Response Status Code: " . $ex->getStatusCode());
			error_log("Error Code: " . $ex->getErrorCode());
			error_log("Error Type: " . $ex->getErrorType());
			error_log("Request ID: " . $ex->getRequestId());
		}
	
	}
	

	add_action('init', 'inpay_callback');
	
}
