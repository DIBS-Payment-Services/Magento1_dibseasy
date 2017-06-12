<?php

/**
 * Class DibsEasyPayment_Api_Resource_Payment
 */
class Dibs_EasyPayment_Api_Service_Payment extends Dibs_EasyPayment_Api_Service{

    /** @var Dibs_EasyPayment_Api_Service_Action_Payment_Create  */
    protected $create;

    /** @var Dibs_EasyPayment_Api_Service_Action_Payment_Find  */
    protected $find;

    /** @var Dibs_EasyPayment_Api_Service_Action_Payment_Charge  */
    protected $charge;

    /** @var Dibs_EasyPayment_Api_Service_Action_Payment_Cancel  */
    protected $cancel;

    /**
     * DibsEasyPayment_Api_Resource_Payment constructor.
     *
     * @param Dibs_EasyPayment_Api_Client $client
     */
    public function __construct(Dibs_EasyPayment_Api_Client $client)
    {
        parent::__construct($client);
        $this->create = new Dibs_EasyPayment_Api_Service_Action_Payment_Create($this);
        $this->find = new Dibs_EasyPayment_Api_Service_Action_Payment_Find($this);
        $this->charge = new Dibs_EasyPayment_Api_Service_Action_Payment_Charge($this);
        $this->cancel = new Dibs_EasyPayment_Api_Service_Action_Payment_Cancel($this);

    }

    /**
     * @param $paymentId
     *
     * @return Dibs_EasyPayment_Api_Response
     */
    public function find($paymentId)
    {
       $result = $this->find->request($paymentId);
       return $result;
    }

    /**
     * @param $params
     *
     * @return Dibs_EasyPayment_Api_Response
     */
    public function create($params)
    {
        $result = $this->create->request($params);
        return $result;
    }

    /**
     * @param $chargeId
     * @param $params
     *
     * @return Dibs_EasyPayment_Api_Response
     */
    public function charge($chargeId, $params)
    {
        $result = $this->charge->request($chargeId, $params);
        return $result;
    }

    /**
     * @param $paymentId
     * @param $params
     *
     * @return Dibs_EasyPayment_Api_Response
     */
    public function cancel($paymentId, $params)
    {
        $result = $this->cancel->request($paymentId, $params);
        return $result;
    }











}