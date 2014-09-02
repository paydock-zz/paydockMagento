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
 
class Paydock_Bitcoin_PaymentController extends Mage_Core_Controller_Front_Action {

	public function redirectAction()
	{
        $bitcoin = Mage::getModel('bitcoin/bitcoin');
        $res = $bitcoin->getInvoiceUrl();

		if(!isset($res['error'])){
			header('location:'.$res['formActionURL']);exit;
		} else {
			$this->_redirect('bitcoin/payment/failure');
		}
	}
	
	public function notifyAction()
	{
		$api_key = Mage::getStoreConfig( 'payment/bitcoin/api_key' );

        $status = isset($_POST['status'])?$_POST['status']:'';
        if($status == 'confirmed'){
            $order_id = (int)$_POST['reference'];
            $paid_amount = $_POST['price'];
    		$ipn_digest = $_POST['ipn_digest'];

            $query = $_POST['uuid'].$_POST['status'].$_POST['price'];
            $hash = hash_hmac("sha256", $query, $api_key);

            if ($ipn_digest == $hash) {
                //success transaction
				$order->getPayment()->registerCaptureNotification( $paid_amount );
				$order->getPayment()->setTransactionId($_POST['uuid']);
				
				$return_msg = 'TransactionID: ' . $_POST['uuid'];
				$order->addStatusToHistory($order->getStatus(), $return_msg);
				$order->save();
				//mail('vnphpexpert@gmail.com', 'Magento Paydock Success: ' . $paid_amount . ': ' . $_POST['uuid']);
            } else {
                //failed transaction
				//mail('vnphpexpert@gmail.com', 'Magento Paydock Error 1: ' . $paid_amount . ': ' . $_POST['uuid']);
            }
        } else {
			//mail('vnphpexpert@gmail.com', 'Magento Paydock Error 2: ' . $status . ': ' . $_POST['uuid']);
		}
	}
	
	public function failureAction()
	{
		$checkout = Mage::getSingleton('checkout/session');
       	$lastOrderId = $checkout->getLastRealOrderId();
		$lastQuoteId = $checkout->getLastQuoteId();
		
		$orderModel = Mage::getModel('sales/order')->loadByIncrementId($lastOrderId);

        if($orderModel->canCancel()) {
            $quote = Mage::getModel('sales/quote')->load($lastQuoteId);
            $quote->setIsActive(true)->save();
            
            $orderModel->cancel();
            $orderModel->setStatus('canceled');
            $orderModel->save();

            Mage::getSingleton('core/session')->setBitcoinError('1');
            Mage::getSingleton('checkout/session')->setFirstTimeChk('0');
			$this->_redirect('checkout/onepage');
        } else {
			$this->_redirect('checkout/cart');
		}
	}
}