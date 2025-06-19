<?php 
class Start {
    public $posSignature;
    public $notifyUrl;
    public $redirectUrl;
    public $apiKey;
    public $isLive;
    public $backUrl;

    
    function __construct(){
        //
    }

        // Send request json
        protected function sendRequest($jsonStr) {
            if(!isset($this->apiKey) || is_null($this->apiKey)) {
                throw new \Exception('INVALID_APIKEY');
                exit;
            }

            $jsonDataObj = json_decode($jsonStr);
            switch ($variable = $jsonDataObj->payment->instrument->type) {
                case 'bnpl.paypo':
                    $url = $this->isLive ? 'https://uat-secure.netopia-payments.com/api/payment/bnpl/start' : 'https://uat-secure.netopia-payments.com/api/payment/bnpl/start'; // sandbox url is NOT CORRECRT!!
                    break;
                case 'bnpl.oney':
                    $url = $this->isLive ? 'https://secure.netopia-payments.com/api/payment/bnpl/start' : 'https://secure.netopia-payments.com/api/payment/bnpl/start'; // sandbox url is NOT CORRECRT!!
                    break;
                default:
                    $url = $this->isLive ? 'https://secure.mobilpay.ro/pay/payment/card/start' : 'https://secure.sandbox.netopia-payments.com/payment/card/start';
                    break;
            }
            
            
            $ch = curl_init($url);
            
            $headers  = [
                'Authorization: '.$this->apiKey,
                'Content-Type: application/json'
            ];

            $payload = $jsonStr; // json DATA
            
            // Attach encoded JSON string to the POST fields
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                  
      
            // Return response instead of outputting
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            // Set the content type to application/json
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            // Execute the POST request
            $result = curl_exec($ch);

            
            if (!curl_errno($ch)) {
                switch ($http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE)) {
                    case 200:  # OK
                        $arr = array(
                            'status'  => 1,
                            'code'    => $http_code,
                            'message' => "You send your request, successfully.",
                            'data'    => json_decode($result)
                        );

                        switch ($jsonDataObj->payment->instrument->type) {
                            case 'bnpl.paypo':
                                if($jsonDataObj->order->amount < 50) {
                                    $arr['status'] = 405;
                                    $arr['message'] = "Paypo este disponibil doar pentru comenzi de peste 50 RON";
                                    $arr['data'] = null;
                                } else {
                                    $arr['message'] .= ' Veti fi redirectionat către Paypo';
                                }
                                break;
                                case 'bnpl.oney':
                                if($jsonDataObj->order->amount < 450) {
                                    $arr['status'] = 405;
                                    $arr['message'] = "Oney este disponibil doar pentru comenzi de peste 450 RON";
                                    $arr['data'] = null;
                                } elseif($jsonDataObj->order->amount > 12000) {
                                    $arr['status'] = 405;
                                    $arr['message'] = "Oney este disponibil doar pentru comenzi mai mici de 12.000 RON.";
                                    $arr['data'] = null;
                                } else {
                                    $arr['message'] .= ' Veti fi redirectionat către Oney';
                                }
                                break;
                            default:
                                $arr['message'] .= ' Veti fi redirectionat catre pagina de plata NETOPIA Payment.';
                                break;
                        }
                    break;
                    case 404:  # Not Found 
                        $arr = array(
                            'status'  => 0,
                            'code'    => $http_code,
                            'message' => "You send request to wrong URL",
                            'data'    => json_decode($result)
                        );
                    break;
                    case 400:  # Bad Request
                        $arr = array(
                            'status'  => 0,
                            'code'    => $http_code,
                            'message' => "You send Bad Request",
                            'data'    => json_decode($result)
                        );
                    break;
                    case 401:  # Authorization required
                        $arr = array(
                            'status'  => 0,
                            'code'    => $http_code,
                            'message' => "Authorization required",
                            'data'    => json_decode($result)
                        );
                    break;
                    case 405:  # Method Not Allowed
                        $arr = array(
                            'status'  => 0,
                            'code'    => $http_code,
                            'message' => "Your method of sending data are not Allowed",
                            'data'    => json_decode($result)
                        );
                    break;
                    default:
                        $arr = array(
                            'status'  => 0,
                            'code'    => $http_code,
                            'message' => "Opps! Something is wrong, verify how you send data & try again!!!",
                            'data'    => json_decode($result)
                        );
                    break;
                }
            } else {
                $arr = array(
                    'status'  => 0,
                    'code'    => 0,
                    'message' => "Opps! There is some problem, you are not able to send data!!!"
                );
            }
            
            // Close cURL resource
            curl_close($ch);
            
            $finalResult = json_encode($arr, JSON_FORCE_OBJECT);
            return $finalResult;
        }
}