<?php

/**
 * Class Dibs_EasyCheckout_Helper_Data
 */
class Dibs_EasyCheckout_Helper_Data extends Mage_Core_Helper_Abstract
{
    // For now we support only SEK
    protected $_supportedCurrencies = ['SEK','NOK','DKK', 'EUR', 'USD'];

    protected $_supportedLanguages = ['en-GB','sv-SE','nb-NO','da-DK'];

    /**
     * @return bool
     */
    public function isEasyCheckoutAvailable()
    {
        $result = false;

        $checkoutEnabled = (bool)(int) Mage::getStoreConfig(Dibs_EasyCheckout_Model_Config::XML_CONFIG_PATH_ENABLED);
        $quoteCurrency = $this->getQuote()->getQuoteCurrencyCode();
        $quoteCurrencySupported = in_array($quoteCurrency, $this->_supportedCurrencies);

        if ($checkoutEnabled && $quoteCurrencySupported) {
            $result = true;
        }

        return $result;
    }

    /**
     * @return mixed
     */
    public function getEnvironment()
    {
        return Mage::getStoreConfig(Dibs_EasyCheckout_Model_Config::XML_CONFIG_PATH_ENVIRONMENT);
    }

    /**
     * @return mixed
     */
    public function getLiveSecret()
    {
        return Mage::getStoreConfig(Dibs_EasyCheckout_Model_Config::XML_CONFIG_PATH_LIVE_SECRET_KEY);
    }

    /**
     * @return mixed
     */
    public function getTestSecret()
    {
        return Mage::getStoreConfig(Dibs_EasyCheckout_Model_Config::XML_CONFIG_PATH_TEST_SECRET_KEY);
    }

    /**
     * @return mixed
     */
    public function getLiveCheckoutKey()
    {
        return Mage::getStoreConfig(Dibs_EasyCheckout_Model_Config::XML_CONFIG_PATH_LIVE_CHECKOUT_KEY);
    }

    /**
     * @return mixed
     */
    public function getTestCheckoutKey()
    {
        return Mage::getStoreConfig(Dibs_EasyCheckout_Model_Config::XML_CONFIG_PATH_TEST_CHECKOUT_KEY);
    }

    /**
     * @return mixed
     */
    public function getNewOrderStatus()
    {
        return Mage::getStoreConfig(Dibs_EasyCheckout_Model_Config::XML_CONFIG_PATH_NEW_ORDER_STATUS);
    }

    /**
     * @return mixed
     */
    public function getShippingCarrierId()
    {
        return Mage::getStoreConfig(Dibs_EasyCheckout_Model_Config::XML_CONFIG_PATH_CARRIER);
    }

    /**
     * @return mixed
     */
    public function getTermsAndConditionsUrl()
    {
        $result = Mage::getStoreConfig(Dibs_EasyCheckout_Model_Config::XML_CONFIG_PATH_TERMS_CONDITIONS_LINK);
        return $result;
    }

    /*
     * @return mixed
     */
    public function getInvoiceFeeProductId() {
        $result = Mage::getStoreConfig(Dibs_EasyCheckout_Model_Config::XML_CONFIG_PATH_PRODUCT_INVOICE_FEE_ID);
        return $result;
    }

    /**
     * @return mixed
     */
    public function getAllowedCustomerTypes()
    {
        return Mage::getStoreConfig(Dibs_EasyCheckout_Model_Config::XML_CONFIG_PATH_CUSTOMER_TYPE);
    }
	
	/**
     * @return mixed
     */
    public function getAutoCapture()
    {
        $_autoCapture = Mage::getStoreConfig(Dibs_EasyCheckout_Model_Config::XML_CONFIG_PATH_AUTO_CAPTURE);
		if($_autoCapture){
			return 'true';
		}else{
			return 'false';
		}
    }
	
	/**
     * @return mixed
     */
    public function getMerchantTermsUrl()
    {
        $result = Mage::getStoreConfig(Dibs_EasyCheckout_Model_Config::XML_CONFIG_PATH_MERCHANT_TERMS_URL);
        return $result;
    }

    /**
     * @return mixed|string
     */
    public function getCheckoutLanguage()
    {
        $language = Dibs_EasyCheckout_Model_Config::DEFAULT_CHECKOUT_LANGUAGE;
        $currentStoreLang = str_replace('_', '-', Mage::app()->getLocale()->getLocaleCode());
        if (in_array($currentStoreLang, $this->_supportedLanguages)) {
            $language = $currentStoreLang;
        }

        return $language;
    }

    /**
     * @return string
     */
    public function getEasyCheckoutJsUrl()
    {
        $environment = $this->getEnvironment();
        switch ($environment) {
            case Dibs_EasyCheckout_Model_Config::CONFIG_API_ENVIRONMENT_TEST:
                $result = Dibs_EasyCheckout_Model_Config::DIBS_CHECKOUT_JS_URL_TEST;
                break;
            case Dibs_EasyCheckout_Model_Config::CONFIG_API_ENVIRONMENT_LIVE:
                $result = Dibs_EasyCheckout_Model_Config::DIBS_CHECKOUT_JS_URL_LIVE;
                break;
            default:
                $result = '';
        }

        return $result;
    }

    /**
     * @return string
     */
    public function getSecretKey()
    {
        $environment = $this->getEnvironment();
        switch ($environment) {
            case Dibs_EasyCheckout_Model_Config::CONFIG_API_ENVIRONMENT_TEST:
                $result = $this->getTestSecret();
                break;
            case Dibs_EasyCheckout_Model_Config::CONFIG_API_ENVIRONMENT_LIVE:
                $result = $this->getLiveSecret();
                break;
            default:
                $result = '';
        }

        return $result;
    }

    /**
     * @return mixed|string
     */
    public function getCheckoutKey()
    {
        $environment = $this->getEnvironment();
        switch ($environment) {
            case Dibs_EasyCheckout_Model_Config::CONFIG_API_ENVIRONMENT_TEST:
                $result = $this->getTestCheckoutKey();
                break;
            case Dibs_EasyCheckout_Model_Config::CONFIG_API_ENVIRONMENT_LIVE:
                $result = $this->getLiveCheckoutKey();
                break;
            default:
                $result = '';
        }

        return $result;
    }

    /**
     * @return bool
     */
    public function isTestEnvironmentEnabled()
    {
        $result = false;
        $currentEnv = $this->getEnvironment();
        if ($currentEnv == Dibs_EasyCheckout_Model_Config::CONFIG_API_ENVIRONMENT_TEST) {
            $result = true;
        }

        return $result;
    }

    /**
     * @return mixed
     */
    public function getQuoteDibsEasyPaymentId()
    {
        return $this->getQuote()->getDibsEasyPaymentId();
    }

    /**
     * @return Mage_Checkout_Model_Session
     */
    public function getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * @return Mage_Sales_Model_Quote|null
     * @throws Exception
     */
    public function getQuote()
    {
        $result = null;
        if ($this->getCheckout()) {
            $result = $this->getCheckout()->getQuote();
        }
        if (empty($result)) {
            $message = $this->__('Cart is empty');
            throw new Exception($message);
        }
        return $result;
    }
    
    public function getCartUrl() {
        return Mage::getSingleton('checkout/session');
    }
   
    public function formatPrice($price) {
        return Mage::getModel('directory/currency')->formatTxt($price, array('display' => Zend_Currency::NO_SYMBOL));
    }
}
