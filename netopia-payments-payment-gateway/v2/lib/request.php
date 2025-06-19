<?php 
include_once('start.php');

class Request extends Start {
    public $authenticationToken;
    public $ntpID;
    public $jsonRequest;

    function __construct(){
        parent::__construct();
    }

    public function setConfig($configData) {
        $config = array(
            'emailTemplate' => (string) isset($configData['emailTemplate']) ? $configData['emailTemplate'] : 'confirm',
            'notifyUrl'     => (string) $configData['notifyUrl'],
            'redirectUrl'   => (string) $configData['redirectUrl'],
            'language'      => (string) isset($configData['language']) ? $configData['language'] : 'RO'
        );
        return $config;
    }

    public function setPayment($threeDSecusreData, $chosenPaymentMethod, $installment = 1) {
        // Set 3 as number of installments for BNPL Oney // Hardcoded for now - Temporary
        if($chosenPaymentMethod == 'bnpl.oney') {
            $isntallment_count = $installment;
        } else {
            $isntallment_count = 1;
        }

        if(!is_null($threeDSecusreData)) {
            $threeDSecusreData = json_decode($threeDSecusreData);
            $threeDSecusreData->IP_ADDRESS = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : "127.0.0.1";
        } else {
            $threeDSecusreData = null;
        }
        
        $payment = array(
            'instrument' => [
                'type' => (string) $chosenPaymentMethod
            ],
            'options' => [
                'installments' => (int) $isntallment_count,
                'bonus'        => (int) 0
            ],
            'data' =>  $threeDSecusreData
        );
        return $payment;
    }

    /**
     * Set the order
     */
    public function setOrder($orderData) { 
        $order = array(
            'ntpID'         => (string) null, 
            'posSignature'  => (string) $this->posSignature,
            'dateTime'      => (string) date("c", strtotime(date("Y-m-d H:i:s"))),
            'description'   => (string) $orderData->description,
            'orderID'       => (string) $orderData->orderID,
            'amount'        => (float)  $orderData->amount,
            'currency'      => (string) $orderData->currency,
            'billing'       => [
                'email'         => (string) $orderData->billing->email,
                'phone'         => (string) $orderData->billing->phone,
                'firstName'     => (string) $orderData->billing->firstName,
                'lastName'      => (string) $orderData->billing->lastName,
                'city'          => (string) $orderData->billing->city,
                'country'       => (int)    $orderData->billing->country,
                'state'         => (string) $orderData->billing->state,
                'postalCode'    => (string) $orderData->billing->postalCode,
                'details'       => (string) $orderData->billing->details
            ],
            'shipping'      => [
                'email'         => (string) $orderData->shipping->email,
                'phone'         => (string) $orderData->shipping->phone,
                'firstName'     => (string) $orderData->shipping->firstName,
                'lastName'      => (String) $orderData->shipping->lastName,
                'city'          => (string) $orderData->shipping->city,
                'country'       => (int)    $orderData->shipping->country,
                'state'         => (string) $orderData->shipping->state,
                'postalCode'    => (string) $orderData->shipping->postalCode,
                'details'       => (string) $orderData->shipping->details
            ],
            'products' => $orderData->products,
            'installments'  => array(
                                    'selected'  => (int) 1,
                                    'available' => [(int) 0]
                            ),
            'data'       => [
                'api'   => $orderData->data->api,
                'platform'   => $orderData->data->platform,
                'wordpress' => $orderData->data->wordpress,
                'wooCommerce' => $orderData->data->wooCommerce,
                'plugin' => $orderData->data->plugin
            ]
        );
        return $order;
    }


    /**
     * Set the request to payment
     * @output json
     */
    public function setRequest($configData, $orderData, $chosenPaymentMethod, $installment = 1, $threeDSecusreData = null) {
        $startArr = array(
          'config'  => $this->setConfig($configData),
          'payment' => $this->setPayment($threeDSecusreData, $chosenPaymentMethod, $installment),
          'order'   => $this->setOrder($orderData)
      );
      
      // make json Data 
      return json_encode($startArr);
      
    }

    public function startPayment(){
    // Un commented for debuging
    //   echo "<pre>";
    //   var_dump($this->jsonRequest);
    //   echo "<hr>";
    //   echo $this->apiKey;
    //   echo "<hr>";
    //   echo $this->posSignature;
    //   echo "</pre>";
      $result = $this->sendRequest($this->jsonRequest);
      return($result);
    }    
}