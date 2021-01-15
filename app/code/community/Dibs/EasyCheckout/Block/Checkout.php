<?php

/**
 * Class Dibs_EasyCheckout_Block_Checkout
 */
class Dibs_EasyCheckout_Block_Checkout extends Mage_Core_Block_Template
{
    /**
     * @return mixed
     */
    public function getPaymentId()
    {
        $paymentId = Mage::registry('dibs_easy_payment_id');
        return $paymentId;
    }

    /**
     * @return mixed|string
     */
    public function getCheckoutKey()
    {
        return $this->getDibsCheckoutHelper()->getCheckoutKey();
    }

    /**
     * @return mixed|string
     */
    public function getCheckoutLanguage()
    {
        return $this->getDibsCheckoutHelper()->getCheckoutLanguage();
    }

    /**
     * @return string
     */
    public function getDibsCheckoutJsUrl()
    {
        return $this->getDibsCheckoutHelper()->getEasyCheckoutJsUrl();
    }

    /**
     * @return Dibs_EasyCheckout_Helper_Data
     */
    protected function getDibsCheckoutHelper()
    {
        /** @var Dibs_EasyCheckout_Helper_Data $helper */
        $helper = Mage::helper('dibs_easycheckout');
        return $helper;
    }

    /**
     * @return string
     */
    public function getDibsCheckoutValidateUrl()
    {
        return Mage::getUrl('dibseasy/checkout/validate');
    }

    /**
     * @return array
     */
    public function getCartProducts() {
        $quote = Mage::helper('checkout/cart')->getCart()->getQuote();
        $value = [];
        foreach ($quote->getAllItems() as $item) {
			
			/**
			 * to remove duplicate products with configurable products in cart
			 * modified by - Niteen Barde
			 */
			if (!$item->getParentItemId()) {
				$price = $item->getParentItemId()?  $item->getParentItem()->getPrice(): $item->getPrice();
				$rowTotal = $item->getRowTotal();
				$formattedPrice = str_replace('.', ',', Mage::getModel('directory/currency')->formatTxt($price, array('display' => Zend_Currency::NO_SYMBOL)));
				$subtotal = str_replace('.', ',', Mage::getModel('directory/currency')->formatTxt($price, array('display' => Zend_Currency::NO_SYMBOL)));
				
				$rowTotal = str_replace('.', ',', Mage::getModel('directory/currency')->formatTxt($rowTotal, array('display' => Zend_Currency::NO_SYMBOL)));
				
				$value[]= array (
				'id' => $item->getId(),
				'product_url' => $item->getProduct()->getProductUrl(),
				'name' => $item->getName(),
				'quantity' => $item->getQty(),
				'price' => $formattedPrice,
				'subtotal' => $rowTotal,
				'thumb_url' =>  $this->getProductThumbnailUrl($item->getProduct())->__toString());
			}
        }
        return (object)$value;
    }

    public function getUpdateCartUrl() {
        return Mage::getUrl('dibseasy/checkout/updateItem');
    }

    /**
     * 
     * @param type $product
     * @return string            )
     */
    protected function getProductThumbnailUrl($product)
    {
        return Mage::helper('catalog/image')->init($product, 'thumbnail')->resize(82, 82);
    }
    
    public function getUpdateViewUrl() {
        return Mage::getUrl('dibseasy/checkout/UpdateView');
    }
    
    public function getCartUrl() {
        return Mage::getUrl('checkout/cart');
    }
    
    public function getSessionError() {
        
        $smessages = Mage::getSingleton('checkout/session')->getMessages()->getItems();
        $output = NULL;
        foreach ($smessages as $smessage) {
        $output .= $smessage->getText();
        }
        //return Mage::getSingleton('checkout/session');
    }
	
}
