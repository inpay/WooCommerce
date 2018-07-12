<?php

/**
 * Copyright (c) 2015 InPay S.A.
 *
 */

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	
	function inpay_callback() {
		if(isset($_GET['inpay_callback'])) {
			global $woocommerce;
			
			require(plugin_dir_path(__FILE__).'bp_lib.php');
			
			$gateways = $woocommerce->payment_gateways->payment_gateways();
			if (!isset($gateways['inpay'])) {
				error_log('InPay plugin not enabled in WooCommerce');
				return;
			}
			$bp = $gateways['inpay'];
			$valid = bpVerifyNotification( $bp->settings['secretApiKey'] );

			if (!$valid) {
				error_log("Not valid notification");
            } else {
				$orderId = $_POST["orderCode"];
				$order = new WC_Order( $orderId );

				switch($_POST['status']) {
					case 'new':
                        $order->add_order_note( __('Awaiting payment.', 'woothemes') );
                        break;
					case 'received':
                        $order->add_order_note( __('InPay bitcoin payment processing. Awaiting confirmation and completed status.', 'woothemes') );
						break;
					case 'confirmed':
						if ( in_array($order->status, array('on-hold', 'pending', 'failed' ) ) ) {
							$order->payment_complete();		
							processOrderFBA($bp, $order);
							$order->add_order_note( __('InPay bitcoin payment completed. Payment credited to your merchant account.', 'woothemes') );
						}
						break;
					case 'expired':
					case 'invalid':
						$order->add_order_note( __('Bitcoin payment is invalid for this order!', 'woothemes') );
						$order->update_status('failed');
						break;
                    case 'refund':
                        $order->add_order_note( __('Bitcoin payment is refunded for this order!', 'woothemes') );
                        $order->update_status('failed');
                        break;
				}
			}
		}
	}
	
	function processOrderFBA($inpay, $order) {
        global $bpfbaOptions;
		if (!$inpay->settings['fbaEnabled']) {
			return;
        }
			
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
		foreach ($items as $i) {
			$product = get_product($i['product_id']);
			if (strlen($product->get_sku())) {
				$hasSkus = true;
            } else {
				$hasBlanks = true;
				continue;
			}
			$orderItems[] = array(
				'currency' => get_woocommerce_currency(),
				'value' => $i['line_subtotal'],
				'sku' => $product->get_sku(),
				'quantity' => $i['qty']);
		}				
		if (!$hasSkus) {
			return true; // nothing to do
        }
		$prefix = ($order->shipping_address_1) ? 'shipping_' : 'billing_';
		$address = array(
			'name' => $order->{$prefix . 'first_name'}.' '.$order->{$prefix . 'last_name'},
			'line1' => $order->{$prefix . 'address_1'},
			'line2' => $order->{$prefix . 'address_2'},
			'line3' => '',
			'city' => $order->{$prefix . 'city'},
			'state' => $order->{$prefix . 'state'},
			'country' => $order->{$prefix . 'country'},
			'zip' => $order->{$prefix . 'postcode'},
			'phone' => $order->billing_phone
        ); // there is no shipping_phone
		if (strlen($order->{$prefix . 'company'})) {
			$address['name'] = $order->{$prefix . 'company'}.' c/o '.$address['name'];
        }
				
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
			APPLICATION_VERSION
        );
		
		$items = array();
		$orderItemId=1;
		foreach($orderItems as $i) {
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
