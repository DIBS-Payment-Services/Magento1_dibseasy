<?php

/**
 * Class Dibs_EasyCheckout_Model_Checkout
 */
class Dibs_EasyCheckout_Model_Checkout extends Mage_Core_Model_Abstract
{
    /**
     * @param Mage_Sales_Model_Quote $quote
     *
     * @return null
     * @throws Dibs_EasyCheckout_Model_Exception
     */
    public function createPaymentId(Mage_Sales_Model_Quote $quote)
    {
        $paymentId = null;
        /** @var Dibs_EasyCheckout_Model_Api $api */
        $api = Mage::getModel('dibs_easycheckout/api');

        /** @var Dibs_EasyPayment_Api_Response $paymentResponse */
        $paymentId = $api->createPayment($quote);

        if ($paymentId) {
            $quote->setDibsEasyPaymentId($paymentId);
            $quote->setDibsEasyGrandTotal($quote->getGrandTotal());
            $quote->save();
        }
        return $paymentId;
    }

    /**
     * @param Mage_Sales_Model_Quote $quote
     * @param Dibs_EasyCheckout_Model_Api_Payment $payment
     *
     * @return bool
     */
    public function validatePayment(Mage_Sales_Model_Quote $quote, Dibs_EasyCheckout_Model_Api_Payment $payment)
    {
        $result = false;
        /** @var Dibs_EasyCheckout_Model_Api $api */
        $api = Mage::getModel('dibs_easycheckout/api');
        if (//$payment->getOrderDetails()->getData('amount') == $api->getDibsQuoteGrandTotal($quote) &&
            $payment->getOrderDetails()->getData('reference') == $quote->getId()
            && $payment->getPaymentId() == $quote->getDibsEasyPaymentId()
            && $payment->getOrderDetails()->getData('currency') == $quote->getQuoteCurrencyCode()
        ) {
            $result = true;
        }
        return $result;
    }

    /**
     * @param Mage_Sales_Model_Quote $quote
     * @param Dibs_EasyCheckout_Model_Api_Payment $payment
     *
     * @return Mage_Sales_Model_Order
     */
    public function createOrder(Mage_Sales_Model_Quote $quote, Dibs_EasyCheckout_Model_Api_Payment $payment)
    {
        $helper = Mage::helper('dibs_easycheckout');
        if($payment->getPaymentDetails()->getPaymentType() == 'INVOICE' 
                && $helper->getInvoiceFeeProductId()) {
            $this->addInvoiceFeeProduct($helper->getInvoiceFeeProductId());
        }
         $quote->collectTotals();
        if ($quote->getCustomerId()) {
            $customer = $this->_loadCustomer($quote->getCustomerId());
            $quote->setCustomer($customer);
            $quote->setCheckoutMethod(Mage_Checkout_Model_Type_Onepage::METHOD_CUSTOMER);
        } else {
            $quote->setCheckoutMethod(Mage_Checkout_Model_Type_Onepage::METHOD_GUEST);
        }
        $quote->setDibsEasyIsCreatingPayment(true);
        $this->_setPaymentMethod($quote, $payment);
        /** @var Dibs_EasyCheckout_Model_Api $api */
        // Temporari disable validation
        /*
        $api = Mage::getModel('dibs_easycheckout/api');
        $quoteDibsTotal = $api->getDibsIntVal($quote->getGrandTotal());
        $reservedDibsAmount = $payment->getSummary()->getData('reservedAmount');
        if ($quoteDibsTotal > $reservedDibsAmount) {
            $reservedDibsAmountRegular = $api->convertDibsValToRegular($reservedDibsAmount);
            $helper = $this->_getHelper();
            $errorText = 'Reserved payment amount is not correct. Reserved amount %s - order amount %s';
            $message = $helper->__($errorText, $reservedDibsAmountRegular, $quote->getGrandTotal());
            throw new Dibs_EasyCheckout_Model_Exception($message);
        }*/

        if(empty($payment->getSummary()->getData('reservedAmount'))) {
            $message = $helper->__('Reserved amount is emapty');
            throw new Dibs_EasyCheckout_Model_Exception($message);
        }

        $quote->setDibsEasyGrandTotal($quote->getGrandTotal());
        if ($quote->getCheckoutMethod() == Mage_Checkout_Model_Type_Onepage::METHOD_GUEST) {
            $this->_prepareGuestCustomerQuote($quote);
            $quote->setCustomerIsGuest(1);
            $quote->setCustomerGroupId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);
        }
        Mage::dispatchEvent('dibs_easy_checkout_quote_before_create_order', array(
            'quote' => $quote,
            'payment' => $payment,
        ));
        $service = Mage::getModel('sales/service_quote', $quote);
        $service->submitAll();
        $order = $this->_loadOrderByKey($quote->getId());
        if (!$order->getId()) {
            Mage::throwException($this->getHelper()->__('Order cannot be created, cart not valid') . ' ' . $quote->getId());
        }

        // update orederid in Easy portal
        /** @var Dibs_EasyCheckout_Model_Api $api */
        $checkoutData = $payment->getCheckout()->getData();
        $params = ['reference' => $order->getIncrementId(),
                   'checkoutUrl' => $checkoutData['url']];
        /** @var Dibs_EasyCheckout_Model_Api $api */
        $api = Mage::getModel('dibs_easycheckout/api');
        $api->updateReference($payment->getPaymentId(), $params);

        // Update Order
        /** @var $order Mage_Sales_Model_Order */
        $oderStatus = $this->_getHelper()->getNewOrderStatus();
        $order->setStatus($oderStatus)->setState('processing');

        // Set Order Dibs Payment Id
        $order->setDibsEasyPaymentId($quote->getDibsEasyPaymentId());

        $order->save();

        $order->sendNewOrderEmail();

        $quote->setIsActive(false)
            ->save();

        /** @var Dibs_EasyCheckout_Helper_Data $helper */
        $helper = Mage::helper('dibs_easycheckout');
        // Set Checkout Success Data
        $checkout = $helper->getCheckout();
        $checkout->setLastOrderId($order->getId());
        $checkout->setLastQuoteId($quote->getId());
        $checkout->setLastSuccessQuoteId($quote->getId());

        return $order;
    }

    public function getQuote() {
        return Mage::getSingleton('checkout/session')->getQuote();
    }

    /**
     * Set shipping address to quote stored in Easy
     */
    public function changeShippingAddress() {
		try {
			$api = Mage::getModel('dibs_easycheckout/api');
			$paymentId = $this->getQuote()->getDibsEasyPaymentId();
			$payment = $api->findPayment($paymentId);
			$quote = $this->getQuote();
			$this->_prepareQuoteBillingAddress($quote, $payment);
			$this->_prepareQuoteShippingAddress($quote, $payment);
			$shippingAddress = $quote->getShippingAddress();
			$shippingAddress->collectShippingRates();
			$this->getQuote()->save();
			
			/* To set shipping method and rate on address change event */
			$quoteShippingMethod = $quote->getShippingAddress()->getShippingMethod();
			if (empty($quoteShippingMethod)) {
				$shippingmethods = $this->getShippinMethods();		
				foreach ($shippingmethods as $shippingMethodCode => $shippingMethod) {
					$this->_setShippingMethod($shippingMethodCode);
					$api->updateCart($quote, $paymentId);
					continue;
				}
			}
		} catch(Exception $e) {
			Mage::log($e->getMessage());
		}
    }

    /**
     * Get shipping rates
     * 
     * @return array
     */
    protected function getShippinMethods() {
         $quote = $this->getQuote();
         $shippingAddress = $quote->getShippingAddress();
         $shippingMethods = array();
        if($shippingAddress->getPostcode() && $shippingAddress->getCity()) { 
            $activeMethodIsSet = false;
            foreach ($shippingAddress->getGroupedAllShippingRates() as $group) {
               foreach ($group as $rate) {
                  $shippingMethods[$rate->getCode()] = array('code' => $rate->getCode(),
                  'price' => $this->_getHelper()->formatPrice($rate->getPrice()),
                  'method_title' => $rate->getMethodTitle(),
                  'carrier_title' =>  $rate->getCarrierTitle(),
                  'active' => $rate->getCode() == $shippingAddress->getShippingMethod() ? $activeMethodIsSet = 1 : 0    
                 );
              }
            }
          if(!$activeMethodIsSet && $shippingMethods) {
              $current = current($shippingMethods);
              $current['active'] = 1;
              $shippingRateCode = key($shippingMethods);
              $shippingMethods[$shippingRateCode] = $current;
              $this->_setShippingMethod($shippingRateCode);
           }
         // no shipping methods for current address
         if(!$quote->isVirtual() && empty($shippingMethods)) {
            throw new Exception ('No available shipping methods for this address');
         }
     }
        return $shippingMethods;
   }

    /*
     * Get all totals from quote
     * 
     * @return array
     */
    protected function getTotals() {
        $quote = $this->getQuote();
        $totals = array();
        $totals['subtotal'] = array('id' => 'subtotal', 'value' => $this->_getHelper()->formatPrice($quote->getSubtotal()), 'label'=>'Subtotal');
        $shippingRate = $quote->getShippingAddress()->getShippingRateByCode($quote->getShippingAddress()->getShippingMethod());
        if($shippingRate) {
           $totals['shipping_method'] = array('id' => 'shipping_method', 'value' => $shippingRate->getCarrierTitle(). ' - ' . $shippingRate->getMethodTitle() , 'label'=>'Shipping');
        }
        $tax = $quote->getShippingAddress()->getTaxAmount();
        $currency = $quote->getQuoteCurrencyCode();
        $taxHtml = '';
        if($tax > 0) {
            $taxHtml = '<br><span id="dibs-easy-incl-tax">(Incl. <span id="dibs-easy-incl-tax-amount">  '
                       . $tax . ' </span>  <span id="dibs-easy-incl-tax-symbol">' 
                       . $currency  .'</span> tax)</span>';
        }
        $discountAmount = $quote->getShippingAddress()->getDiscountAmount();
        if(abs($discountAmount) > 0) {
            $discountAmount = $this->_getHelper()->formatPrice($discountAmount);
            $discountDescription = $quote->getShippingAddress()->getDiscountDescription();

            if ($discountDescription) {
                $totals['discount'] = ['id' => 'discount', 'label' => 'Discount (' . $discountDescription . ')', 'value' => $discountAmount];
            } else {
                $totals['discount'] = ['id' => 'discount', 'label' => 'Discount', 'value' => $discountAmount];
            }

        }
        $totals['grand_total'] = array('id' => 'grand_total', 'value' =>$this->_getHelper()->formatPrice($quote->getGrandTotal()), 'label'=>'Grand Total' . $taxHtml);
        return $totals;
    }

    /**
    * Get values needed for checkout Easy page
    */
    public function getGridValues() {
        $values = array();
        $values['shipping_methods'] = $this->getShippinMethods();
        $values['totals'] = $this->getTotals(); 
        return $values;
    }

    /*
     * Chech if quote was changed 
     */
    protected function cartEasyUpdateIsNeeded() {
      $quote = $this->getQuote();
      $api = Mage::getModel('dibs_easycheckout/api');
      $paymentId = $quote->getDibsEasyPaymentId();
      $payment = $api->findPayment($paymentId);
      if($payment->getOrderDetails()->getData('amount') == $api->getDibsIntVal($quote->getGrandTotal())) {
          return false;
      } else {
          return true;
      }
    }

    public function start() {
        if($this->cartEasyUpdateIsNeeded()) {
            $api = Mage::getModel('dibs_easycheckout/api');
            $paymentId = $this->getQuote()->getDibsEasyPaymentId();
            $quote = $this->getQuote();
            $api->updateCart($quote, $paymentId);
        }
    }

    /**
     * Set new shipping method on checkout page
     * 
     * @param type $shippingRateCode
     */
    public function setShippingMethod($shippingRateCode) {
        $paymentId = $this->getQuote()->getDibsEasyPaymentId();
        $quote = $this->getQuote();
        $this->_setShippingMethod($shippingRateCode);
        $api = Mage::getModel('dibs_easycheckout/api');
        $api->updateCart($quote, $paymentId);
   }

    /**
     * @param Mage_Sales_Model_Quote $quote
     * @param Dibs_EasyCheckout_Model_Api_Payment $payment
     *
     * @return $this
     */
    protected function _prepareQuoteBillingAddress(
        Mage_Sales_Model_Quote $quote,
        Dibs_EasyCheckout_Model_Api_Payment $payment
    ) {
        $paymentBillingAddress = $payment->getBillingAddress();
        $paymentBillingAddress = $paymentBillingAddress->getData();
        if (empty($paymentBillingAddress)) {
            $paymentBillingAddress = $payment->getShippingAddress();
        }
        $billingAddress = $quote->getBillingAddress();
        //$billingRegionCode  = $paymentBillingAddress->getData('postalCode');
		$billingRegionCode  = $paymentBillingAddress['postalCode'];
		
        $billingAddress->setFirstname($payment->getPrivatePerson()->getData('firstName'));
        $billingAddress->setLastname($payment->getPrivatePerson()->getData('lastName'));
        
		//$billingAddress->setStreet($paymentBillingAddress->getStreetsArray());
		$addressLine1  = $paymentBillingAddress['addressLine1'];
		$billingAddress->setStreet($addressLine1);
		
        //$billingAddress->setPostcode($paymentBillingAddress->getData('postalCode'));
		$billingAddress->setPostcode($billingRegionCode);
		
        //$billingAddress->setCity($paymentBillingAddress->getData('city'));
		$billingAddress->setCity($paymentBillingAddress['city']);
		
        //$billingAddress->setCountryId($this->getCountryId($paymentBillingAddress->getData('country')));
		$billingAddress->setCountryId($this->getCountryId($paymentBillingAddress['country']));
		
        $billingAddress->setEmail($payment->getPrivatePerson()->getData('email'));
        $billingAddress->setTelephone($payment->getPrivatePerson()->getTelephone());
        $billingAddress->setCompany($payment->getCompany()->getData('name'));
        if ($billingRegionCode) {
            $billingRegionId =$this->getRegionId($billingAddress->getCountryId(), $billingRegionCode);
            $billingAddress->setRegionId($billingRegionId);
        }
        $billingAddress->setShouldIgnoreValidation(true);
        return $this;
    }

    /**
     * @param Mage_Sales_Model_Quote $quote
     * @param Dibs_EasyCheckout_Model_Api_Payment $payment
     */
    protected function _prepareQuoteShippingAddress(
        Mage_Sales_Model_Quote $quote,
        Dibs_EasyCheckout_Model_Api_Payment $payment
    ) {

        $shippingAddress = $quote->getShippingAddress();
        $shippingAddress->setFirstname($payment->getPrivatePerson()->getData('firstName'));
        $shippingAddress->setLastname($payment->getPrivatePerson()->getData('lastName'));
        $shippingAddress->setStreet($payment->getShippingAddress()->getStreetsArray());
        $shippingAddress->setPostcode($payment->getShippingAddress()->getData('postalCode'));
        $shippingAddress->setCity($payment->getShippingAddress()->getData('city'));
        $shippingAddress->setCountryId($this->getCountryId($payment->getShippingAddress()->getData('country')));
        $shippingAddress->setEmail($payment->getPrivatePerson()->getData('email'));
        $shippingAddress->setTelephone($payment->getPrivatePerson()->getTelephone());
        $shippingAddress->setCompany($payment->getCompany()->getData('name'));
        $shippingRegionCode = $payment->getShippingAddress()->getData('postalCode');
        $shippingAddress->setCollectShippingRates(true);
        if ($shippingRegionCode) {
            $shippingRegionId =$this->getRegionId($shippingAddress->getCountryId(), $shippingRegionCode);
			$shippingAddress->setRegionId($shippingRegionId);
        }
        $shippingAddress->setShouldIgnoreValidation(true);
    }

    /**
     * @param string $countryCode
     *
     * @return null
     */
    protected function getCountryId($countryCode = '')
    {
        $result = null;
        if (!empty($countryCode)) {
            $result = Mage::getModel('directory/country')->loadByCode($countryCode)->getId();
        }
        return $result;
    }

    /**
     * @param string $countryId
     * @param string $regionCode
     *
     * @return null
     */
    protected function getRegionId($countryId = '', $regionCode = '')
    {
        $result = null;
        if (!empty($countryId) && !empty($regionCode)){
            $result = Mage::getModel('directory/region')->loadByCode($regionCode, $countryId)->getId();
        }
        return $result;
    }

    /**
     * @param Mage_Sales_Model_Quote $quote
     */
    protected function _setPaymentMethod(Mage_Sales_Model_Quote $quote, Dibs_EasyCheckout_Model_Api_Payment $dibsPayment)
    {
        $quotePayment = $quote->getPayment();
        $quotePayment->importData(array('method' => Dibs_EasyCheckout_Model_Config::PAYMENT_CHECKOUT_METHOD));
        $quotePayment->setData('dibs_easy_payment_type', $dibsPayment->getPaymentDetails()->getPaymentType());
        $quotePayment->setData('dibs_easy_cc_masked_pan', $dibsPayment->getPaymentDetails()->getMaskedPan());
        $quotePayment->setData('cc_last_4', $dibsPayment->getPaymentDetails()->getCcLast4());
        $quotePayment->setData('cc_exp_month', $dibsPayment->getPaymentDetails()->getCcExpMonth());
        $quotePayment->setData('cc_exp_year', $dibsPayment->getPaymentDetails()->getCcExpYear());
    }

    /**
     * @param Mage_Sales_Model_Quote $quote
     */
    protected function _setShippingMethod($shippingRateCode)
    {
        $quote = $this->getQuote();
        if (!$quote->getIsVirtual() && $shippingAddress = $quote->getShippingAddress()) {
            $shippingAddress = $quote->getShippingAddress();
            $shippingAddress->setCollectShippingRates(true)
                ->collectShippingRates();
            $shippingAddress->setShippingMethod($shippingRateCode)->save();
        }
        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();
        $quote->save();
    }

    /**
     * @param Mage_Sales_Model_Quote $quote
     */
    protected function _prepareGuestCustomerQuote(Mage_Sales_Model_Quote $quote)
    {
        $billing    = $quote->getBillingAddress();
        $shipping   = $quote->isVirtual() ? null : $quote->getShippingAddress();
        /** @var $customer Mage_Customer_Model_Customer */
        $customer = $quote->getCustomer();
        $customerBilling = $billing->exportCustomerAddress();
        $customer->addAddress($customerBilling);
        $billing->setCustomerAddress($customerBilling);
        $customerBilling->setIsDefaultBilling(true);
        if ($shipping && !$shipping->getSameAsBilling()) {
            $customerShipping = $shipping->exportCustomerAddress();
            $customer->addAddress($customerShipping);
            $shipping->setCustomerAddress($customerShipping);
            $customerShipping->setIsDefaultShipping(true);
        } else {
            $customerBilling->setIsDefaultShipping(true);
        }
        $customer->setFirstname($customerBilling->getFirstname());
        $customer->setLastname($customerBilling->getLastname());
        $customer->setEmail($customerBilling->getEmail());
        $quote->setCustomer($customer);
    }

    /**
     * @return Dibs_EasyCheckout_Helper_Data
     */
    protected function _getHelper()
    {
        return Mage::helper('dibs_easycheckout');
    }

    /**
     * @param $id
     *
     * @return Mage_Customer_Model_Customer
     */
    protected function _loadCustomer($id)
    {
        return Mage::getModel('customer/customer')->load($id);
    }

    /**
     * @param $id
     * @param string $key
     *
     * @return Mage_Sales_Model_Order
     */
    protected function _loadOrderByKey($id, $key = 'quote_id')
    {
        return Mage::getModel('sales/order')->load($id, $key);
    }

    /**
     * 
     * @param type $productId
     */
    protected function addInvoiceFeeProduct($productId)
    {
        $cart = Mage::getSingleton('checkout/cart');
        $cart->init();
        $product = Mage::getModel('catalog/product')->load($productId);
        $paramater = array('product' => $productId,
                            'qty' => '1',
                            'form_key' => Mage::getSingleton('core/session')->getFormKey(),
                            'options' => []);       
        $request = new Varien_Object();
        $request->setData($paramater);
        if($product->getId()) {
            $cart->addProduct($product, $request);
        }
        $cart->save();
    }
}
