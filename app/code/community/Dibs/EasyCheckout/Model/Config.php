<?php

/**
 * Class Dibs_EasyCheckout_Model_Config
 */
class Dibs_EasyCheckout_Model_Config
{

    const PAYMENT_CHECKOUT_METHOD = 'dibs_easy_checkout';

    const XML_CONFIG_PATH_TERMS_CONDITIONS_LINK = 'payment/dibs_easy_checkout/terms_and_conditions_link';

    const XML_CONFIG_PATH_CUSTOMER_TYPE = 'payment/dibs_easy_checkout/allowed_customer_types';

    const XML_CONFIG_PATH_ENVIRONMENT = 'payment/dibs_easy_checkout/environment';

    const XML_CONFIG_PATH_LIVE_SECRET_KEY = 'payment/dibs_easy_checkout/live_secret_key';

    const XML_CONFIG_PATH_TEST_SECRET_KEY = 'payment/dibs_easy_checkout/test_secret_key';

    const XML_CONFIG_PATH_LIVE_CHECKOUT_KEY = 'payment/dibs_easy_checkout/live_checkout_key';

    const XML_CONFIG_PATH_TEST_CHECKOUT_KEY = 'payment/dibs_easy_checkout/test_checkout_key';

    const XML_CONFIG_PATH_NEW_ORDER_STATUS = 'payment/dibs_easy_checkout/new_order_status';

    const XML_CONFIG_PATH_CARRIER = 'payment/dibs_easy_checkout/carrier';

    const XML_CONFIG_PATH_ENABLED = 'payment/dibs_easy_checkout/enabled';
    
    const XML_CONFIG_PATH_PRODUCT_INVOICE_FEE_ID = 'payment/dibs_easy_checkout/invoice_fee_product_id';

    const CONFIG_CUSTOMER_TYPE_B2B = 'B2B';

    const CONFIG_CUSTOMER_TYPE_B2C = 'B2C';

    const CONFIG_CUSTOMER_TYPE_ALL_B2B_DEFAULT = 'B2B_B2C';

    const CONFIG_CUSTOMER_TYPE_ALL_B2C_DEFAULT = 'B2C_B2B';

    const CONFIG_API_ENVIRONMENT_TEST = 'test';

    const CONFIG_API_ENVIRONMENT_LIVE = 'live';

    const DIBS_CHECKOUT_JS_URL_TEST = 'https://test.checkout.dibspayment.eu/v1/checkout.js?v=1';

    const DIBS_CHECKOUT_JS_URL_LIVE = 'https://checkout.dibspayment.eu/v1/checkout.js?v=1';

    const DEFAULT_CHECKOUT_LANGUAGE = 'en-GB';

    const DIBS_EASY_SHIPPING_METHOD = 'dibs_easy_freeshipping_dibs_easy_freeshipping';
	
	const XML_CONFIG_PATH_AUTO_CAPTURE = 'payment/dibs_easy_checkout/auto_capture';
	
	const XML_CONFIG_PATH_MERCHANT_TERMS_URL = 'payment/dibs_easy_checkout/merchant_terms_url';
    
}