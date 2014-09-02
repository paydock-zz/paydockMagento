<?php

/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   Paydock
 * @package    Paydock_Bitcoin
 * @copyright  Copyright (c) 2014 Vnphpexpert.com
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Paydock_Bitcoin_Model_Bitcoin extends Mage_Payment_Model_Method_Abstract
{
    /**
    * unique internal payment method identifier
    *
    * @var string [a-z0-9_]
    */
    protected $_code = 'bitcoin';
    
	protected $_order_url	= 'https://paydock.io/api/orders';
    protected $_invoice_url	= 'https://paydock.io/invoice/';
	
	/**
     * Return Order place direct url
     *
     * @return string
     */
    public function getOrderPlaceRedirectUrl()
    {
        return Mage::getUrl('bitcoin/payment/redirect', array('_secure' => true));
    }
    
    public function getInvoiceUrl()
    {
        $checkout = Mage::getSingleton('checkout/session');
        $reference = $checkout->getLastRealOrderId();
        $order = Mage::getModel('sales/order')->loadByIncrementId($reference);
        
        $currency   = $order->getOrderCurrencyCode();
		$BAddress = $order->getBillingAddress();
		
		$gateway_url = $this->_order_url;
        $amount = number_format($order->getGrandTotal(), 2, '.', '');

        $callbackUrl = Mage::getUrl('bitcoin/payment/notify');
        $successUrl = Mage::getUrl('checkout/onepage/success');

        $order_items = array();
		$items = $order->getAllItems();
		if ($items)
        {
            foreach($items as $item)
            {
            	if ($item->getParentItem()) continue;
				$order_items[] = array('name'=>$item->getName(), 'quantity'=>(int)$item->getQtyOrdered(), 'price'=>number_format($item->getPrice(), 2, '.', ''));
            }
        }
		
		$shipping_cost = $order->getBaseShippingAmount();
		
		if($shipping_cost > 0)
			$order_items[] = array('name'=>'Shipping', 'quantity'=>1, 'price'=>number_format($shipping_cost, 2, '.', ''));
        
		$request = array('currency'=>$currency, 
							'price'=>$amount, 
							'order_items'=>$order_items,
							'notification_url'=>$callbackUrl,
							'return_url'=>$successUrl,
							'reference'=>$reference
							);


        $request = json_encode($request);

        $result = $this->sendRequest($gateway_url, $request);

        $result = json_decode($result);

        if (isset($result->uuid)) {
			$uuid = $result->uuid;
			$formActionURL = $this->_invoice_url.$uuid;
			
			return array(
				'formActionURL' => $formActionURL
			);
		}
		else
		{
			return array(
				'error' => 1
			);
		}
        
    }
	
	public function sendRequest ($gateway_url, $request)
    {
		$user_id = Mage::getStoreConfig( 'payment/bitcoin/user_id' );
    	$api_key = Mage::getStoreConfig( 'payment/bitcoin/api_key' );
		
		$headers = array(
						'Content-Type:application/json',
						'Authorization: Basic '. base64_encode("$user_id:$api_key"),
						'X-PayDock-Plugin: Magento'
						);

        $CR = curl_init();
        curl_setopt($CR, CURLOPT_URL, $gateway_url);
		curl_setopt($CR, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($CR, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($CR, CURLOPT_TIMEOUT, 30);
		curl_setopt($CR, CURLOPT_POST, true);
        curl_setopt($CR, CURLOPT_FAILONERROR, true);
        curl_setopt($CR, CURLOPT_POSTFIELDS, $request);
        curl_setopt($CR, CURLOPT_RETURNTRANSFER, true);

        //actual curl execution perfom
        $result = curl_exec($CR);
        $error = curl_error($CR);

        // on error - die with error message
        if (!empty($error)) {
            die($error);
        }

        curl_close($CR);

        return $result;
    }
	
    /**
     * Return true if the method can be used at this time
     *
     * @return bool
     */
    public function isAvailable($quote=null)
    {
        if (!parent::isAvailable($quote)) {
            return false;
        }
		return true;
    }
}