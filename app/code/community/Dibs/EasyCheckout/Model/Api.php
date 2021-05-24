<?php

/**
 * Class Dibs_EasyCheckout_Model_Api
 */
class Dibs_EasyCheckout_Model_Api extends Mage_Core_Model_Abstract
{

    /**
     * @var Dibs_EasyPayment_Api_Client
     */
    protected $apiClient;

    /**
     * @var Dibs_EasyPayment_Api_Service_Payment
     */
    protected $paymentService;

    /**
     * @var Dibs_EasyPayment_Api_Service_Refund
     */
    protected $refundService;

    /**
     * @param Mage_Sales_Model_Quote $quote
     *
     * @return null
     */
    public function createPayment(Mage_Sales_Model_Quote $quote)
    {
        $result = null;
        $paymentService = $this->getPaymentService();
        $createPaymentParams = $this->_getCreatePaymentParams($quote);
        $response = $paymentService->create($createPaymentParams);
        $result = $response->getResponseDataObject()->getData('paymentId');
        return $result;
    }

    /**
     * @param $paymentId
     *
     * @return Dibs_EasyCheckout_Model_Api_Payment|null
     */
    public function findPayment($paymentId)
    {
        $result = null;
        $paymentService = $this->getPaymentService();
        $response = $paymentService->find($paymentId);
        $result = new Dibs_EasyCheckout_Model_Api_Payment($response->getResponseDataObject()->getData('payment'));
        return $result;
    }

    /**
     * @param Mage_Sales_Model_Order_Invoice $invoice
     * @param $amount
     *
     * @return mixed|null
     */
    public function chargePayment(Mage_Sales_Model_Order_Invoice $invoice, $amount)
    {
        $result = null;
        $paymentId = $invoice->getOrder()->getDibsEasyPaymentId();
        $paymentService = $this->getPaymentService();
        $chargeParams = $this->_getChargePaymentParams($invoice, $amount);
        $response = $paymentService->charge($paymentId, $chargeParams);
        $result = $response->getResponseDataObject()->getData('chargeId');
        return $result;
    }

    /**
     * @param $chargeId
     * @param Mage_Sales_Model_Order_Creditmemo $creditmemo
     * @param $amount
     *
     * @return mixed|null
     */
    public function refundPayment($chargeId, Mage_Sales_Model_Order_Creditmemo $creditmemo, $amount)
    {
        $result = null;
        $refundService = $this->getRefundService();
        $chargeParams = $this->_getRefundPaymentParams($creditmemo, $amount);
        $response = $refundService->charge($chargeId, $chargeParams);
        $result = $response->getResponseDataObject()->getData('refundId');
        return $result;
    }

    /**
     * update cart items
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param $paymentId
     */
    public function updateCart(Mage_Sales_Model_Quote $quote, $paymentId)
    {
        $result = null;
        $paymentService = $this->getPaymentService();
        $params = $this->_getUpadtePaymentParams($quote);
        $response = $paymentService->update($paymentId, $params);
    }

    /**
     * update refernceid in Easy portal
     *
     * @param $paymentId
     * @param $params
     */
    public function updateReference($paymentId, $params)
    {
        $result = null;
        $paymentService = $this->getPaymentService();
        $response = $paymentService->updateReference($paymentId, $params);
    }

    /**
     * @return Dibs_EasyPayment_Api_Service_Payment
     */
    public function getPaymentService()
    {
        if (is_null($this->paymentService)) {
            $apiClient = $this->_getApiClient();
            $this->paymentService = new Dibs_EasyPayment_Api_Service_Payment($apiClient);
        }
        return $this->paymentService;
    }

    /**
     * @return Dibs_EasyPayment_Api_Service_Payment|Dibs_EasyPayment_Api_Service_Refund
     */
    public function getRefundService()
    {
        if (is_null($this->paymentService)) {
            $apiClient = $this->_getApiClient();
            $this->paymentService = new Dibs_EasyPayment_Api_Service_Refund($apiClient);
        }

        return $this->paymentService;
    }

    /**
     * @return Dibs_EasyPayment_Api_Client
     */
    protected function _getApiClient()
    {
        if (is_null($this->apiClient)) {
            $secretKey = $this->_getDibsCheckoutHelper()->getSecretKey();
            $isTestEnvironment = $this->_getDibsCheckoutHelper()->isTestEnvironmentEnabled();
            $this->apiClient = new Dibs_EasyPayment_Api_Client($secretKey, $isTestEnvironment);
        }
        return $this->apiClient;
    }

    /**
     * @param Mage_Sales_Model_Order_Creditmemo $creditmemo
     * @param $amount
     *
     * @return array
     */
    protected function _getRefundPaymentParams(Mage_Sales_Model_Order_Creditmemo $creditmemo, $amount)
    {
        $refundOrderItems = $this->_getCreditMemoItems($creditmemo);
        $params = [
            'amount' => $this->getDibsIntVal($amount),
            'orderItems' => $refundOrderItems
        ];

        return $params;
    }

    /**
     * @param Mage_Sales_Model_Order_Invoice $invoice
     * @param $amount
     *
     * @return array
     */
    protected function _getChargePaymentParams(Mage_Sales_Model_Order_Invoice $invoice, $amount)
    {
        $invoiceItems = $this->_getInvoiceItems($invoice);
        $params = [
            'amount' => $this->getDibsIntVal($amount),
            'orderItems' => $invoiceItems
        ];

        return $params;
    }

    /**
     * @param Mage_Sales_Model_Quote $quote
     *
     * @return array
     */
    protected function _getCreatePaymentParams(Mage_Sales_Model_Quote $quote)
    {
      $params = [
            'order' => [
                'items'     =>  $this->_getQuoteItems($quote),
                //'amount'    =>  $this->getDibsQuoteGrandTotal($quote),
                'amount'    =>  $this->getAmountAll($quote),
                'currency'  =>  $quote->getQuoteCurrencyCode(),
                'reference' =>  $quote->getEntityId()
            ],
            'checkout' => [
              'url' => Mage::getUrl('dibseasy/checkout', array( 'qid' => $quote->getId(), '_secure'=>true))]];
        if(Mage::getModel('customer/session')->isLoggedIn()
              && $this->validateAddress()) {
        $phoneNumber = $this->extractPhone();
        $params['checkout']['consumer'] =
              ['email'=> $this->getCustomer()->getEmail(),
                    'shippingAddress'=> $this->getCustomerShippingAddress(),
                    'phoneNumber'=> [
                        'prefix'=> $phoneNumber['prefix'],
                        'number'=> $phoneNumber['phone']],
                    'privatePerson'=> [
                        'firstName'=> $this->getCustomer()->getFirstname(),
                        'lastName'=> $this->getCustomer()->getLastname()]
               ];
        $params['checkout']['merchantHandlesConsumerData'] = true;
      }
      $this->setInvoiceFee($params, $quote);
      $this->setTermsAndConditionsUrl($params);
      $this->setCustomerTypes($params);
	  $this->setAutoCapture($params);
	  $this->setMerchantTermsUrl($params);
      return $params;
    }

    /**
     * @param Mage_Sales_Model_Quote $quote
     * 
     * @return array
     */
    protected function _getUpadtePaymentParams(Mage_Sales_Model_Quote $quote)
    {
        $result = array();
        $result['amount'] =round($quote->getGrandTotal(), 2) * 100;
        $result['items'] = $this->_getQuoteItems($quote);
        $result['shipping']['costSpecified'] = true;
        return $result;
    }

    /**
     * @param $params
     *
     * @return $this
     */
    private function setTermsAndConditionsUrl(&$params)
    {
        $params['checkout']['termsUrl'] = $this->_getDibsCheckoutHelper()->getTermsAndConditionsUrl();
        return $this;
    }

    /**
     * @param $params
     *
     * @return $this
     */
    private function setCustomerTypes(&$params)
    {
        $multipleCustomerTypes = [
            Dibs_EasyCheckout_Model_Config::CONFIG_CUSTOMER_TYPE_ALL_B2C_DEFAULT,
            Dibs_EasyCheckout_Model_Config::CONFIG_CUSTOMER_TYPE_ALL_B2B_DEFAULT
        ];
        $customerTypesAllowed = $this->_getDibsCheckoutHelper()->getAllowedCustomerTypes();
        $default = $customerTypesAllowed;
        if (in_array($customerTypesAllowed, $multipleCustomerTypes)) {
            switch ($customerTypesAllowed) {
                case Dibs_EasyCheckout_Model_Config::CONFIG_CUSTOMER_TYPE_ALL_B2C_DEFAULT:
                    $default = Dibs_EasyCheckout_Model_Config::CONFIG_CUSTOMER_TYPE_B2C;
                    break;
                case Dibs_EasyCheckout_Model_Config::CONFIG_CUSTOMER_TYPE_ALL_B2B_DEFAULT:
                    $default = Dibs_EasyCheckout_Model_Config::CONFIG_CUSTOMER_TYPE_B2B;
                    break;
            }
        }
        $params['checkout']['supportedConsumerTypes'] = str_replace('_', ',', $customerTypesAllowed);
        $params['checkout']['defaultConsumerType'] = $default;
        return $this;
    }

    /**
     * @param Mage_Sales_Model_Quote $quote
     *
     * @return array
     */
    protected function _getQuoteItems(Mage_Sales_Model_Quote $quote)
    {
        $result = [];
        $items = $quote->getAllItems();
		/* Item block */
        /** @var Mage_Sales_Model_Quote_Item $item */
        foreach ($items as $item) {
            if ($this->_isNotChargeable($item)) {
                continue;
            }
            $result[] = $this->_getOrderLineItem($item);
        }
		
		/* Shipment block */
        $shippingAmount = (double)$quote->getShippingAddress()->getShippingInclTax();
        if ($shippingAmount > 0) {
            $carrierReference = $quote->getShippingAddress()->getShippingMethod();
            $carrierName = $quote->getShippingAddress()->getShippingDescription();
            $shippingAddress = $quote->getShippingAddress();
            $result[] = $this->_getShippingLineItem($shippingAddress, $carrierReference, $carrierName);
        }
		
		/* Discount block */
		$discountAmount = 0;
		foreach ($items as $item) {
			$discountAmount += $item->getBaseDiscountAmount();
        }
		if ($discountAmount > 0) {
			$result[] = $this->_getDiscountItem($quote);
		}
        return $result;
    }
	
	protected function getAmountAll(Mage_Sales_Model_Quote $quote){
		
		$amountArray = $this->_getQuoteItems($quote);
		$sum = 0;
		foreach ($amountArray as $product) {
			$sum += $product['grossTotalAmount'];
		}
		$sum = round($sum,2);
		return $sum;
	}

    /**
     * @param Mage_Sales_Model_Order_Creditmemo $invoice
     *
     * @return array
     */
    protected function _getCreditMemoItems(Mage_Sales_Model_Order_Creditmemo $creditmemo)
    {
        $result = [];
        $items = $creditmemo->getAllItems();
        /** @var Mage_Sales_Model_Order_Creditmemo_Item $item */
        foreach ($items as $item) {
            if ($this->_isNotChargeable($item->getOrderItem())) {
                continue;
            }
            $result[] = $this->_getOrderLineItem($item);
        }

        $shippingAmount = (double)$creditmemo->getShippingInclTax();

        if ($shippingAmount > 0) {
            $carrierReference = $creditmemo->getOrder()->getShippingMethod();
            $carrierName = $creditmemo->getOrder()->getShippingDescription();
            $result[] = $this->_getShippingLineItem($creditmemo, $carrierReference, $carrierName);
        }

        return $result;
    }

    /**
     * @param Mage_Sales_Model_Order_Invoice $invoice
     *
     * @return array
     */
    protected function _getInvoiceItems(Mage_Sales_Model_Order_Invoice $invoice)
    {
        $result = [];
        $items = $invoice->getAllItems();
        /** @var Mage_Sales_Model_Order_Invoice_Item $item */
        foreach ($items as $item) {
            if ($this->_isNotChargeable($item->getOrderItem())) {
                continue;
            }
            $result[] = $this->_getOrderLineItem($item);
        }
        $shippingAmount = (double)$invoice->getShippingInclTax();
        if ($shippingAmount > 0) {
            $carrierReference = $invoice->getOrder()->getShippingMethod();
            $carrierName = $invoice->getOrder()->getShippingDescription();
            $result[] = $this->_getShippingLineItem($invoice, $carrierReference, $carrierName);
        }
        return $result;
    }

    /**
     * @param Mage_Core_Model_Abstract $item
     *
     * @return array
     */
    protected function _getOrderLineItem(Mage_Core_Model_Abstract $item)
    {	
		$quantity = (int)$item->getQty();
		$taxFormat = '1'.str_pad(number_format((float)$item->getTaxPercent(), 2, '.', ''), 5, '0', STR_PAD_LEFT);
		$taxRate = round($item->getTaxPercent() * 100, 2);
		if(Mage::helper('tax')->priceIncludesTax() && Mage::helper('tax')->applyTaxAfterDiscount()) {
			//$productPrice = round( $item->getPriceInclTax()* 100 , 2);
			$productPrice = round($item->getPrice()* 100 , 2);
			$netTotalAmount = round($quantity * $productPrice);
			$grossTotalAmount = round( round($productPrice * ($taxFormat/100)) *  $quantity);
			$taxAmount = $grossTotalAmount - $netTotalAmount;
		}else{
			if(!Mage::helper('tax')->priceIncludesTax()){	
				$productPrice = round( $item->getPrice()* 100, 2);
				$grossTotalAmount = round( ($item->getRowTotal() + $item->getTaxAmount()) * 100, 2);
				$netTotalAmount = round($quantity * $productPrice);
				$taxAmount = $grossTotalAmount - $netTotalAmount;
			}
		}
		
        $name = preg_replace('/[^\w\d\s]*/', '', $item->getName());
        $result = [
            'reference'         =>  $item->getSku(),
            'name'              =>  $name,
            'quantity'          =>  $quantity,
            'unit'              =>  'qty',
            'unitPrice'         =>  $productPrice,
            'taxRate'           =>  $taxRate,
            'taxAmount'         =>  $taxAmount,
            'grossTotalAmount'  =>  $grossTotalAmount,
            'netTotalAmount'    =>  $netTotalAmount
        ];

        return $result;
    }

    protected function _getShippingLineItem($shippingInfo, $carrierReference, $carrierName)
    {
        $tax = ($shippingInfo->getShippingTaxAmount() + $shippingInfo->getShippingHiddenTaxAmount());
        $name = preg_replace('/[^\w\d\s]*/', '', $carrierName);
        $result = [
            'reference'         =>  $carrierReference,
            'name'              =>  $name,
            'quantity'          =>  1,
            'unit'              =>  'units',
            'unitPrice'         =>  $this->getDibsIntVal($shippingInfo->getShippingAmount()),
            'taxRate'           =>  0,
            'taxAmount'         =>  $this->getDibsIntVal($tax),
            'grossTotalAmount'  =>  $this->getDibsIntVal($shippingInfo->getShippingInclTax()),
            'netTotalAmount'    =>  $this->getDibsIntVal($shippingInfo->getShippingAmount()) ,
        ];

        return $result;
    }
	
	protected function _getDiscountItem(Mage_Sales_Model_Quote $quote)
    {
		/*
		$productPrice = round(round(($item->getBasePrice()*100) / $item->getTaxPercent(), 2) * 100);
		$taxFormat = '1'.str_pad(number_format((float)$item->getTaxPercent(), 2, '.', ''), 5, '0', STR_PAD_LEFT);
		$taxRate = $item->getTaxPercent() * 100;
		$netTotalAmount = round($quantity * $productPrice);
		$grossTotalAmount = round(($netTotalAmount * $taxFormat)/100);
		$taxAmount = $grossTotalAmount - $netTotalAmount;
		*/
		
		$items = $quote->getAllItems();
		$totalDiscountAmount = 0;
		foreach($items as $item){
			$totalDiscountAmount += $item->getDiscountAmount() * 100;
		}
		
		$result = [
				'reference'         =>  'discount',
				'name'              =>  $quote->getCouponCode(),
				'quantity'          =>  1,
				'unit'              =>  'units',
				'unitPrice'         =>  -$totalDiscountAmount,
				'taxRate'           =>  0,
				'taxAmount'         =>  0,
				'grossTotalAmount'  =>  -$totalDiscountAmount,
				'netTotalAmount'    =>  -$totalDiscountAmount,
			];
        return $result;
    }

    /*
     * Alpply invoice fee using simple product
     */
    protected function setInvoiceFee(&$params, Mage_Sales_Model_Quote $quote)
    {
        $productInvoiceFeeId = $this->_getDibsCheckoutHelper()->getInvoiceFeeProductId();
        if($productInvoiceFeeId) {
            $productInvoiceFee = Mage::getModel('catalog/product')->load($productInvoiceFeeId);
            $quoteCurrency = $quote->getQuoteCurrencyCode();
            $country = null;
            switch($quoteCurrency) {
                case 'SEK':
                    $country = 'SE';
                break;

                case 'DKK':
                   $country = 'DK';
                break;

                case 'NOK':
                   $country = 'NO';
            }
            $quote->getShippingAddress()->setCountryId($country)->save();
            $price = Mage::helper('tax')->getPrice($productInvoiceFee, $productInvoiceFee->getPrice(), false,
                    $quote->getShippingAddress(), $quote->getBillingAddress());
            if($productInvoiceFee->getId()) {
                $taxPercent = $productInvoiceFee->getTaxPercent();
                $taxAmount = ($productInvoiceFee->getPrice() / 100) * $productInvoiceFee->getTaxPercent();
                $params["paymentMethods"] = [
                            ["name" => "easyinvoice",
                             "fee" => [
                                 'reference'         =>  $productInvoiceFee->getSku(),
                                 'name'              =>  $productInvoiceFee->getName(),
                                 'quantity'          =>  1,
                                 'unit'              =>  'psc',
                                 'unitPrice'         =>  $this->getDibsIntVal($price),
                                 'taxRate'           =>  $this->getDibsIntVal($taxPercent),
                                 'taxAmount'         =>  $this->getDibsIntVal($taxAmount),
                                 'grossTotalAmount'  =>  $this->getDibsIntVal($price),
                                 'netTotalAmount'    =>  $this->getDibsIntVal($price)]]
                           ];
            }
        }
        return $this;
    }

    /**
     * @param Mage_Sales_Model_Quote $quote
     *
     * @return int
     */
    public function getDibsQuoteGrandTotal(Mage_Sales_Model_Quote $quote)
    {
        return $this->getDibsIntVal($quote->getGrandTotal());
    }

    /**
     * @param Mage_Core_Model_Abstract $item
     *
     * @return bool
     */
    protected function _isNotChargeable(Mage_Core_Model_Abstract $item)
    {
        $result = false;
        if ($item->getParentItem()
            && $item->getParentItem()->getProductType() == Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {
            $result = true;
        }

        if ($item->getProductType() == Mage_Catalog_Model_Product_Type::TYPE_BUNDLE) {
            $result = true;
        }

        return $result;
    }

    /**
     * @param Mage_Core_Model_Abstract $item
     *
     * @return int
     */
    protected function _getItemTaxAmount(Mage_Core_Model_Abstract $item)
    {
        $itemTax = (double)$item->getTaxAmount() + (double)$item->getHiddenTaxAmount();
        $result = $this->getDibsIntVal($itemTax);

        return $result;
    }

    /**
     * @param Mage_Core_Model_Abstract $item
     *
     * @return int
     */
    protected function _getItemGrossTotalAmount(Mage_Core_Model_Abstract $item)
    {
        if(Mage::helper('tax')->priceIncludesTax() && Mage::helper('tax')->applyTaxAfterDiscount()) {
            $itemGrossTotal =  ((double)$item->getPriceInclTax() * (int)$item->getQty()) - (double)abs($item->getDiscountAmount());
        } else {
            $itemGrossTotal =  ((double)$item->getPrice() * (int)$item->getQty()) + (double)$item->getTaxAmount() - (double)$item->getDiscountAmount();
        }
        $result = $this->getDibsIntVal($itemGrossTotal);
        return $result;
    }

    /**
     * @param Mage_Core_Model_Abstract $item
     *
     * @return int
     */
    protected function _getItemNetTotalAmount(Mage_Core_Model_Abstract $item)
    {
        $netDiscount = (double)$item->getDiscountAmount() - (double)$item->getHiddenTaxAmount();
        $itemNetTotal = (double)$item->getRowTotalInclTax() - (double)$item->getTaxAmount() - $netDiscount;
        $result = $this->getDibsIntVal($itemNetTotal);

        return $result;
    }

    /**
     * @param $value
     *
     * @return int
     */
    public function getDibsIntVal($value)
    {
        $result = (double)$value * 100;
        return (string)$result;
    }

    /**
     * @param $value
     *
     * @return float
     */
    public function convertDibsValToRegular($value)
    {
        $result = $value / 100;
        return (double)$result;
    }

    /**
     * @return Dibs_EasyCheckout_Helper_Data
     */
    protected function _getDibsCheckoutHelper()
    {
        /** @var Dibs_EasyCheckout_Helper_Data $helper */
        $helper = Mage::helper('dibs_easycheckout');
        return $helper;
    }

    protected function getCustomerShippingAddress() 
    {
        $customerSession = Mage::getModel('customer/session');
        $shippingAddress = array();
        if($customerSession->isLoggedIn()) {
           $customer = $customerSession->getCustomer();
           $address = $customer->getDefaultShippingAddress();
           $shippingAddress = array(
               'addressLine1' => $address->getStreet1(),
               'addressLine2'=> $address->getStreet2(),
               'postalCode'=> $address->getPostcode(),
               'city'=> $address->getCity(),
               'country'=> $address->getCountryModel()->getIso3Code()
           );
        }
        return $shippingAddress;
    }

    protected function getCustomer() 
    {
        return Mage::getModel('customer/session')->getCustomer();
    }

    /**
     * Get phone from customers address
     * 
     * @return string|bool
     */
    protected function extractPhone() 
    {
         $customer = $this->getCustomer();
         $address = $customer->getDefaultShippingAddress();
            $valid = true;
            $prefix = '';
            switch($address->getCountry()) {
                case 'NO':
                    $prefix = '+47';
                break;
                case 'SE':
                    $prefix = '+46';
                break;
                case 'DK':
                    $prefix = '+45';
                break;
                default:
                    $prefix = '';
            }
            $phone = $customer->getPrimaryBillingAddress()->getTelephone();
            $phoneCleaned = str_replace(array('-','(', ')',' '),'', $phone);
            if(empty($prefix)) {
                if(preg_match('/^\+[0-9]{8,15}/', $phoneCleaned) ) {
                    $prefix = substr($phoneCleaned, 0, 3);
                    if(empty($prefix)) {
                        $valid = false;
                    }
                    $postfix = substr($phoneCleaned, 3);
                } else {
                    $valid = false;
                }
            } else {
                 if(preg_match('/^\+?[0-9]{8,15}/', $phoneCleaned) ) {
                    $postfix = substr($phoneCleaned, -9);
                } else {
                   $valid = false;
                }
            }
           $shippingAddress = array(
               'addressLine1' => $address->getStreet1(),
               'addressLine2'=> $address->getStreet2(),
               'postalCode'=> $address->getPostcode(),
               'city'=> $address->getCity(),
               'country'=> $address->getCountryModel()->getIso3Code()
           );
           if($valid) {
              return array('prefix' => $prefix, 'phone' => $postfix);
           }
           else return false;
    }

    protected function validateAddress()
    {
       return $this->getCustomer()->getDefaultShippingAddress()->validate()
              && $this->extractPhone();
    }
	
	/**
     * @param $params
     *
     * @return $this
     */
    private function setAutoCapture(&$params)
    {
        $params['checkout']['charge'] = $this->_getDibsCheckoutHelper()->getAutoCapture();
        return $this;
    }
	
	/**
     * @param $params
     *
     * @return $this
     */
	private function setMerchantTermsUrl(&$params)
    {
        $params['checkout']['merchantTermsUrl'] = $this->_getDibsCheckoutHelper()->getMerchantTermsUrl();
        return $this;
    }

}
