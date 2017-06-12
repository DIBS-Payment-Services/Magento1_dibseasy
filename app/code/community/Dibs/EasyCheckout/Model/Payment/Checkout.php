<?php
/**
 * Copyright (c) 2009-2017 Vaimo Group
 *
 * Vaimo reserves all rights in the Program as delivered. The Program
 * or any portion thereof may not be reproduced in any form whatsoever without
 * the written consent of Vaimo, except as provided by licence. A licence
 * under Vaimo's rights in the Program may be available directly from
 * Vaimo.
 *
 * Disclaimer:
 * THIS NOTICE MAY NOT BE REMOVED FROM THE PROGRAM BY ANY USER THEREOF.
 * THE PROGRAM IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE PROGRAM OR THE USE OR OTHER DEALINGS
 * IN THE PROGRAM.
 *
 * @category    Dibs
 * @package     Dibs_EasyCheckout
 * @copyright   Copyright (c) 2009-2017 Vaimo Group
 */

class Dibs_EasyCheckout_Model_Payment_Checkout extends Mage_Payment_Model_Method_Abstract
{
    protected $_code = Dibs_EasyCheckout_Helper_Data::PAYMENT_CHECKOUT_METHOD;

    protected $_canCapture                  = true;
    protected $_canCapturePartial           = true;

    protected $_canRefund                   = true;
    protected $_canRefundInvoicePartial     = true;

    /**
     * @param Varien_Object $payment
     * @param float $amount
     *
     * @return $this
     * @throws Exception
     */
    public function capture(Varien_Object $payment, $amount) {
        parent::capture($payment, $amount);

        /** @var Mage_Sales_Model_Order_Invoice $invoice */
        $invoice = $payment->getInvoice();

        if (!$invoice){
            $message = $this->getDibsEasyCheckoutHelper()->__('Invoice is not exists');
            throw new Exception($message);
        }

        $chargeId = $this->processCharge($invoice, $amount);

        $payment->setStatus(self::STATUS_APPROVED);
        $payment->setTransactionId($chargeId)
            ->setIsTransactionClosed(1);

        return $this;
    }

    /**
     * @return $this
     */
    public function validate()
    {
        // No validation, it should just work when it gets here
        return $this;
    }

    /**
     * @param Varien_Object $payment
     * @param float $amount
     *
     * @return $this
     * @throws Exception
     */
    public function refund(Varien_Object $payment, $amount) {

        $chargeId = null;
        $creditMemo = $payment->getCreditmemo();
        $invoice = $creditMemo->getInvoice();
        if ($invoice && $invoice->getTransactionId()){
            $chargeId = $invoice->getTransactionId();
        }

        if (empty($chargeId)){
            $message = $this->getDibsEasyCheckoutHelper()->__('Dibs Charge id is empty');
            throw new Exception($message);
        }

        $refundId = $this->processRefund($creditMemo,$amount,$chargeId);
        $payment->setTransactionId($refundId)
            ->setIsTransactionClosed(1);

        return $this;
    }

    /**
     * @param Mage_Sales_Model_Order_Invoice $invoice
     * @param $amount
     *
     * @return mixed|null
     */
    public function processCharge(Mage_Sales_Model_Order_Invoice $invoice, $amount) {
        /** @var Dibs_EasyCheckout_Model_Api $api */
        $api = Mage::getModel('dibs_easycheckout/api');
        $chargeId = $api->chargePayment($invoice, $amount);
        return $chargeId;
    }

    /**
     * @param Mage_Sales_Model_Order_Creditmemo $creditmemo
     * @param $amount
     * @param $chargeId
     *
     * @return mixed|null
     */
    public function processRefund(Mage_Sales_Model_Order_Creditmemo $creditmemo, $amount, $chargeId) {
        /** @var Dibs_EasyCheckout_Model_Api $api */
        $api = Mage::getModel('dibs_easycheckout/api');
        $refundId = $api->refundPayment($chargeId, $creditmemo, $amount);
        return $refundId;
    }

    /**
     * @return Mage_Core_Helper_Abstract
     */
    public function getDibsEasyCheckoutHelper()
    {
        return Mage::helper('dibs_easycheckout');
    }

}