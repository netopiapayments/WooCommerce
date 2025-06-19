<?php
include_once('request.php');

use Firebase\JWT\JWT;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;

class IPN extends Request{
   
    public $activeKey;
    public $posSignatureSet;
    public $hashMethod;
    public $alg;
    public $publicKeyStr;

    // Error code defination
    const E_VERIFICATION_FAILED_GENERAL			= 0x10000101;
    const E_VERIFICATION_FAILED_SIGNATURE		= 0x10000102;
    const E_VERIFICATION_FAILED_NBF_IAT			= 0x10000103;
    const E_VERIFICATION_FAILED_EXPIRED			= 0x10000104;
    const E_VERIFICATION_FAILED_AUDIENCE		= 0x10000105;
    const E_VERIFICATION_FAILED_TAINTED_PAYLOAD	= 0x10000106;
    const E_VERIFICATION_FAILED_PAYLOAD_FORMAT	= 0x10000107;

    const ERROR_TYPE_NONE 		= 0x00;
    const ERROR_TYPE_TEMPORARY 	= 0x01;
    const ERROR_TYPE_PERMANENT 	= 0x02;

    /**
     * available statuses for the purchase class (prcStatus)
     */
    const STATUS_NEW 									= 1;	//0x01; //new purchase status
    const STATUS_OPENED 								= 2;	//OK //0x02; // specific to Model_Purchase_Card purchases (after preauthorization) and Model_Purchase_Cash
    const STATUS_PAID 									= 3;	//OK //0x03; // capturate (card)
    const STATUS_CANCELED 								= 4;	//0x04; // void
    const STATUS_CONFIRMED 								= 5;	//OK //0x05; //confirmed status (after IPN)
    const STATUS_PENDING 								= 6;	//0x06; //pending status
    const STATUS_SCHEDULED 								= 7;	//0x07; //scheduled status, specific to Model_Purchase_Sms_Online / Model_Purchase_Sms_Offline
    const STATUS_CREDIT 								= 8;	//0x08; //specific status to a capture & refund state
    const STATUS_CHARGEBACK_INIT 						= 9;	//0x09; //status specific to chargeback initialization
    const STATUS_CHARGEBACK_ACCEPT 						= 10;	//0x0a; //status specific when chargeback has been accepted
    const STATUS_ERROR 									= 11;	//0x0b; // error status
    const STATUS_DECLINED 								= 12;	//0x0c; // declined status
    const STATUS_FRAUD 									= 13;	//0x0d; // fraud status
    const STATUS_PENDING_AUTH 							= 14;	//0x0e; //specific status to authorization pending, awaiting acceptance (verify)
    const STATUS_3D_AUTH 								= 15;	//0x0f; //3D authorized status, speficic to Model_Purchase_Card
    const STATUS_CHARGEBACK_REPRESENTMENT 				= 16;	//0x10;
    const STATUS_REVERSED 								= 17;	//0x11; //reversed status
    const STATUS_PENDING_ANY 							= 18;	//0x12; //dummy status
    const STATUS_PROGRAMMED_RECURRENT_PAYMENT 			= 19;	//0x13; //specific to recurrent card purchases
    const STATUS_CANCELED_PROGRAMMED_RECURRENT_PAYMENT 	= 20;	//0x14; //specific to cancelled recurrent card purchases
    const STATUS_TRIAL_PENDING							= 21;	//0x15; //specific to Model_Purchase_Sms_Online; wait for ACTON_TRIAL IPN to start trial period
    const STATUS_TRIAL									= 22;	//0x16; //specific to Model_Purchase_Sms_Online; trial period has started
    const STATUS_EXPIRED								= 23;	//0x17; //cancel a not payed purchase 

    /**
     * WooCommerce Order Statuses
     */
    const STATUS_WC_PENDING_PAYMENT = 'wc-pending-payment';
    const STATUS_WC_PENDING = 'wc-pending';
    const STATUS_WC_PROCESSING = 'wc-processing';
    const STATUS_WC_COMPLETED = 'wc-completed';
    const STATUS_WC_ON_HOLD = 'wc-on-hold';
    const STATUS_WC_CANCELED = 'wc-cancelled';
    const STATUS_WC_REFUNDED = 'wc-refunded';
    const STATUS_WC_FAILED = 'wc-failed';
    const STATUS_WC_HOLD = 'wc-hold';
    const STATUS_WC_CANCELLED = 'wc-cancelled';
    const STATUS_WC_PENDING_REVIEW = 'wc-pending-review';
    const STATUS_WC_PROCESSING_REFUND = 'wc-processing-refund';
    
    public function __construct(){
        parent::__construct();
    }

    /**
     * to Verify IPN
     * @return 
     *  - a Json
     */
    public function verifyIPN() {

        /**
        * Define IPN response, 
        * will update base on payment status
        */
        $outputData = array();

        /**
        *  Fetch all HTTP request headers
        */
        $aHeaders = $this->getApacheHeader();
        if(!$this->validHeader($aHeaders)) {
            echo 'IPN__header is not an valid HTTP HEADER' . PHP_EOL;
            exit;
        }

        /**
        *  fetch Verification-token from HTTP header 
        */
        $verificationToken = $this->getVerificationToken($aHeaders);
        if($verificationToken === null)
            {
            echo 'IPN__Verification-token is missing in HTTP HEADER' . PHP_EOL;
            exit;
            }
        
        /**
        * Analising verification token
        * Just to make sure if Type is JWT & Use right encoding/decoding algorithm 
        * Assign following var 
        *  - $headb64, 
        *  - $bodyb64,
        *  - $cryptob64
        */
        $tks = \explode('.', $verificationToken);
        if (\count($tks) != 3) {
            throw new \Exception('Wrong_Verification_Token');
            exit;
        }
        list($headb64, $bodyb64, $cryptob64) = $tks;
        $jwtHeader = json_decode(base64_decode(\strtr($headb64, '-_', '+/')));
        
        if($jwtHeader->typ !== 'JWT') {
            throw new \Exception('Wrong_Token_Type');
            exit; 
        }

        /**
        * check if publicKeyStr is defined
        */
        if(isset($this->publicKeyStr) && !is_null($this->publicKeyStr)){
            $publicKey = openssl_pkey_get_public($this->publicKeyStr);
            if($publicKey === false) {
                echo 'IPN__public key is not a valid public key' . PHP_EOL; 
                exit;
            }
        } else {
            echo "IPN__Public key missing" . PHP_EOL; 
            exit;
        }


        /**
        * Get raw data
        */
        $HTTP_RAW_POST_DATA = file_get_contents('php://input');

        /**
        * The name of the alg defined in header of JWT
        * Just in case we set the default algorithm
        * Default alg is RS512
        */
        if(!isset($this->alg) || $this->alg==null){
            throw new \Exception('IDS_Service_IpnController__INVALID_JWT_ALG');
            exit;
        }
        $jwtAlgorithm = !is_null($jwtHeader->alg) ? $jwtHeader->alg : $this->alg ; // ???? May need to Compare with Verification-token header // Ask Alex

        
        try {
            JWT::$timestamp = time() * 1000; 
        
           /**
            * Decode from JWT
            */
            $objJwt = JWT::decode($verificationToken, $publicKey, array($jwtAlgorithm));
        
            if(strcmp($objJwt->iss, 'NETOPIA Payments') != 0)
                {
                throw new \Exception('IDS_Service_IpnController__E_VERIFICATION_FAILED_GENERAL');
                exit;
                }
            
            /**
             * check active posSignature 
             * check if is in set of signature too
             */
            if(empty($objJwt->aud) || current($objJwt->aud) != $this->activeKey){
                throw new \Exception('IDS_Service_IpnController__INVALID_SIGNATURE'.print_r($objJwt->aud, true).'__'.$this->activeKey);
                exit;
            }
        
            if(!in_array(current($objJwt->aud), $this->posSignatureSet,true)) {
                throw new \Exception('IDS_Service_IpnController__INVALID_SIGNATURE_SET');
                exit;
            }
            
            if(!isset($this->hashMethod) || $this->hashMethod==null){
                throw new \Exception('IDS_Service_IpnController__INVALID_HASH_METHOD');
                exit;
            }
            
            /**
             * GET HTTP HEADER
             */
            $payload = $HTTP_RAW_POST_DATA;
            /**
             * validate payload
             * sutable hash method is SHA512 
             */
            $payloadHash = base64_encode(hash ($this->hashMethod, $payload, true ));
            /**
             * check IPN data integrity
             */
        
            if(strcmp($payloadHash, $objJwt->sub) != 0)
                {
                throw new \Exception('IDS_Service_IpnController__E_VERIFICATION_FAILED_TAINTED_PAYLOAD');
                print_r($payloadHash); // Temporay for Debuging
                exit;
                }
        
            try
                {
                $objIpn = json_decode($payload, false);
                // hear, can make Log for $objIpn
                }
            catch(\Exception $e)
                {
                throw new \Exception('IDS_Service_IpnController__E_VERIFICATION_FAILED_PAYLOAD_FORMAT');
                }
            
            /**
             * Get Order Data
             *  */
            $trxID = $objIpn->order->orderID;
            $trxSplit = explode("_",$trxID);
            $actualOrderId = $trxSplit[0];
            

            switch($objIpn->payment->status)
                {
                
                /**
                 * +----------------------------+
                 * | Most usable payment status |
                 * +----------------------------+
                 */
                
                case self::STATUS_PAID: // capturate (card)
                    /**
                     * payment was paid; deliver goods
                     */
                    $outputData['errorType']	= self::ERROR_TYPE_NONE;
                    $outputData['errorCode']	= null;
                    $outputData['errorMessage']	= "The payment was made and needs to be confirmed.";
                    $this->addOrderNoteAndUpdateStatus($actualOrderId, $trxID,  $outputData['errorMessage'], null, self::STATUS_WC_PROCESSING, $objIpn->payment->message);
                break;
                case self::STATUS_CANCELED: // void
                    /**
                     * payment was cancelled; do not deliver goods
                     */
                    $outputData['errorType']	= self::ERROR_TYPE_TEMPORARY;
                    $outputData['errorCode']	= self::STATUS_CANCELED;
                    $outputData['errorMessage']	= "Payment was cancelled. Do not proceed with the delivery of goods.";

                    $endUserNote = __('Your payment has been cancelled. The order will not be processed.', 'netopiapayments');
                    $this->addOrderNoteAndUpdateStatus($actualOrderId, $trxID,  $outputData['errorMessage'.' | '.$objIpn->payment->message], $endUserNote, self::STATUS_WC_CANCELED, $objIpn->payment->message);
                break;
                case self::STATUS_DECLINED: // declined
                    /**
                     * payment is declined
                     */
                    $outputData['errorType']	= self::ERROR_TYPE_TEMPORARY;
                    $outputData['errorCode']	= self::STATUS_DECLINED;
                    $outputData['errorMessage']	= "Payment was declined";

                    $endUserNote = __('Your payment was declined. Please try again.', 'netopiapayments');
                    $this->addOrderNoteAndUpdateStatus($actualOrderId, $trxID,  $outputData['errorMessage'], $endUserNote, null, $objIpn->payment->message, $objIpn->payment->code);
                break;
                case self::STATUS_FRAUD: // fraud
                    /**
                     * payment status is in fraud, reviw the payment
                     */
                    $outputData['errorType']	= self::ERROR_TYPE_TEMPORARY;
                    $outputData['errorCode']	= self::STATUS_FRAUD;
                    $outputData['errorMessage']	= "Payment under review for potential risk. Hold delivery until confirmation.";

                    $endUserNote = __("Thanks! We’re reviewing your payment and will update you soon.", 'netopiapayments');
                    $this->addOrderNoteAndUpdateStatus($actualOrderId, $trxID,  $outputData['errorMessage'], $endUserNote, self::STATUS_WC_PENDING_REVIEW, $objIpn->payment->message, $objIpn->payment->code);
                break;
                case self::STATUS_3D_AUTH:
                    /**
                     * In STATUS_3D_AUTH the paid purchase need to be authenticate by bank
                     */
                    $outputData['errorType']	= self::ERROR_TYPE_TEMPORARY;
                    $outputData['errorCode']	= self::STATUS_3D_AUTH;
                    $outputData['errorMessage']	= "3D AUTH required";
                break;

                /**
                 * +-----------------------+
                 * | Other patments status |
                 * +-----------------------+
                 */

                case self::STATUS_NEW:
                    /**
                     * STATUS_NEW
                     */
                    $outputData['errorCode']	= self::STATUS_NEW;
                    $outputData['errorMessage']	= "STATUS_NEW";
                break;
                case self::STATUS_OPENED: // preauthorizate (card)
                    /**
                    * preauthorizate (card)
                    */
                    $outputData['errorType']	= self::ERROR_TYPE_TEMPORARY;
                    $outputData['errorCode']	= self::STATUS_OPENED;
                    $outputData['errorMessage']	= "preauthorizate (card)";
                break;
                case self::STATUS_CONFIRMED:
                    /**
                     * payment was confirmed; deliver goods
                     */
                    $outputData['errorType']	= self::ERROR_TYPE_NONE;
                    $outputData['errorCode']	= null;
                    $outputData['errorMessage']	= "Payment completed. Deliver the goods";

                    $endUserNote = __('Your payment has been successfully completed. Your order is now being processed and will be delivered soon.', 'netopiapayments');
                    $this->addOrderNoteAndUpdateStatus($actualOrderId, $trxID, $outputData['errorMessage'], $endUserNote, self::STATUS_WC_COMPLETED, null);
                break;
                case self::STATUS_PENDING:
                    /**
                    * payment in pending
                    */
                    $outputData['errorType']	= self::ERROR_TYPE_TEMPORARY;
                    $outputData['errorCode']	= self::STATUS_PENDING;
                    $outputData['errorMessage']	= "Payment pending";
                break;
                case self::STATUS_SCHEDULED:
                    /**
                     * *!*!*!*
                     */
                    $outputData['errorType']	= self::ERROR_TYPE_TEMPORARY;
                    $outputData['errorCode']	= self::STATUS_SCHEDULED;
                    $outputData['errorMessage']	= "";
                break;
                case self::STATUS_CREDIT: // capturate si apoi refund
                    /**
                     * a previously confirmed payment eas refinded; cancel goods delivery
                     */
                    $outputData['errorType']	= self::ERROR_TYPE_TEMPORARY;
                    $outputData['errorCode']	= self::STATUS_CREDIT;
                    $outputData['errorMessage']	= "Payment refunded. Review delivery status.";

                    $endUserNote = __('Your payment was refunded. Follow your order for updates.', 'netopiapayments');
                    $this->addOrderNoteAndUpdateStatus($actualOrderId, $trxID,  $outputData['errorMessage'], $endUserNote, self::STATUS_WC_REFUNDED, $objIpn->payment->message, $objIpn->payment->code);
                break;
                case self::STATUS_CHARGEBACK_INIT: // chargeback initiat
                     /**
                     * chargeback initiat
                     */
                    $outputData['errorType']	= self::ERROR_TYPE_TEMPORARY;
                    $outputData['errorCode']	= self::STATUS_CHARGEBACK_INIT;
                    $outputData['errorMessage']	= "chargeback initiat";
                break;
                case self::STATUS_CHARGEBACK_ACCEPT: // chargeback acceptat
                    /**
                     * *!*!*!*
                     */
                    $outputData['errorType']	= self::ERROR_TYPE_TEMPORARY;
                    $outputData['errorCode']	= self::STATUS_CHARGEBACK_ACCEPT;
                    $outputData['errorMessage']	= "";
                break;
                case self::STATUS_ERROR: // error
                    /**
                     * payment has an error
                     */
                    $outputData['errorType']	= self::ERROR_TYPE_TEMPORARY;
                    $outputData['errorCode']	= self::STATUS_ERROR;
                    $outputData['errorMessage']	= "Payment has an error";

                    $this->addOrderNoteAndUpdateStatus($actualOrderId, $trxID,  $outputData['errorMessage'].' | '.$objIpn->payment->message, null , null, $objIpn->payment->message, $objIpn->payment->code);
                break;
                case self::STATUS_PENDING_AUTH: // in asteptare de verificare pentru tranzactii autorizate
                    /**
                     * update payment status, last modified date&time in your system
                     */
                    $outputData['errorType']	= self::ERROR_TYPE_TEMPORARY;
                    $outputData['errorCode']	= self::STATUS_PENDING_AUTH;
                    $outputData['errorMessage']	= "specific status to authorization pending, awaiting acceptance (verify)";
                break;
                case self::STATUS_CHARGEBACK_REPRESENTMENT:
                    /**
                     * *!*!*!*
                     */
                    $outputData['errorType']	= self::ERROR_TYPE_TEMPORARY;
                    $outputData['errorCode']	= self::STATUS_CHARGEBACK_REPRESENTMENT;
                    $outputData['errorMessage']	= "";
                break;
                case self::STATUS_REVERSED:
                    /**
                     * *!*!*!*
                     */
                    $outputData['errorType']	= self::ERROR_TYPE_TEMPORARY;
                    $outputData['errorCode']	= self::STATUS_REVERSED;
                    $outputData['errorMessage']	= "";
                break;
                case self::STATUS_PENDING_ANY:
                    /**
                     * *!*!*!*
                     */
                    $outputData['errorType']	= self::ERROR_TYPE_TEMPORARY;
                    $outputData['errorCode']	= self::STATUS_PENDING_ANY;
                    $outputData['errorMessage']	= "";
                break;
                case self::STATUS_PROGRAMMED_RECURRENT_PAYMENT:
                    /**
                     * *!*!*!*
                     */
                    $outputData['errorType']	= self::ERROR_TYPE_TEMPORARY;
                    $outputData['errorCode']	= self::STATUS_PROGRAMMED_RECURRENT_PAYMENT;
                    $outputData['errorMessage']	= "";
                break;
                case self::STATUS_CANCELED_PROGRAMMED_RECURRENT_PAYMENT:
                     /**
                     * *!*!*!*
                     */
                    $outputData['errorType']	= self::ERROR_TYPE_TEMPORARY;
                    $outputData['errorCode']	= self::STATUS_CANCELED_PROGRAMMED_RECURRENT_PAYMENT;
                    $outputData['errorMessage']	= "";
                break;
                case self::STATUS_TRIAL_PENDING: //specific to Model_Purchase_Sms_Online; wait for ACTON_TRIAL IPN to start trial period
                     /**
                     * specific to Model_Purchase_Sms_Online; wait for ACTON_TRIAL IPN to start trial period
                     */
                    $outputData['errorType']	= self::ERROR_TYPE_TEMPORARY;
                    $outputData['errorCode']	= self::STATUS_TRIAL_PENDING;
                    $outputData['errorMessage']	= "specific to Model_Purchase_Sms_Online; wait for ACTON_TRIAL IPN to start trial period";
                break;
                case self::STATUS_TRIAL: //specific to Model_Purchase_Sms_Online; trial period has started
                    /**
                     * specific to Model_Purchase_Sms_Online; trial period has started
                     */
                    $outputData['errorType']	= self::ERROR_TYPE_TEMPORARY;
                    $outputData['errorCode']	= self::STATUS_TRIAL;
                    $outputData['errorMessage']	= "specific to Model_Purchase_Sms_Online; trial period has started";
                break;
                case self::STATUS_EXPIRED: //cancel a not paid purchase
                     /**
                     * cancel a not paid purchase
                     */
                    $outputData['errorType']	= self::ERROR_TYPE_TEMPORARY;
                    $outputData['errorCode']	= self::STATUS_EXPIRED;
                    $outputData['errorMessage']	= "cancel a not payed purchase ";
                break;
                default:
                $outputData['errorType']	= self::ERROR_TYPE_TEMPORARY;
                $outputData['errorCode']	= $objIpn->payment->status;
                $outputData['errorMessage']	= "Unknown";
                }
            
        } catch(\Exception $e)
        {
            $outputData['errorType']	= self::ERROR_TYPE_PERMANENT;
            $outputData['errorCode']	= ($e->getCode() != 0) ? $e->getCode() : self::E_VERIFICATION_FAILED_GENERAL;
            $outputData['errorMessage']	= $e->getMessage();
        }

        return $outputData;
    }

    /**
    *  Fetch all HTTP request headers
    */
    public function getApacheHeader() {
        $aHeaders = apache_request_headers(); // May be in some host is closed / Maybe not working on NGINX
        // $aHeaders = file_get_contents('php://input');
        return $aHeaders;
    }

    /**
    * if header exist in HTTP request
    * and is a valid header
    * @return bool 
    */
    public function validHeader($httpHeader) {
        if(!is_array($httpHeader)){
            return false;
        } else {
            if(!array_key_exists('Verification-token', $httpHeader)){
                return false;
            }
        }
        return true;
    }

    /**
    *  fetch Verification-token from HTTP header 
    */
    public function getVerificationToken($httpHeader) {
        foreach($httpHeader as $headerName=>$headerValue)
            {
                if(strcasecmp('Verification-token', $headerName) == 0)
                {
                    $verificationToken = $headerValue;
                    return $verificationToken;
                }
            }
        return null;
    }

    public function addOrderNoteAndUpdateStatus($orderID, $trxID, $adminNote = null, $userNote = null, $newStatus = null, $customMsgStatus = null, $ntpPaymentCode = null)
    {
        $order = new WC_Order( $orderID );
        
        //Add Order Note for Admin
        if($adminNote != null) {
            if($customMsgStatus != null) {
                $adminNote = $adminNote . ' | ' . $customMsgStatus;
            }
            
            if($trxID != null) {
                $adminNote = $adminNote . ' | ' . __('Transaction ID: ', 'netopiapayments') . $trxID;
            }

            if($ntpPaymentCode != null) {
                $adminNote = $adminNote . ' | ' . __('Payment Code: ', 'netopiapayments') . $ntpPaymentCode;
            }
            
            $order->add_order_note($adminNote);
        }

        //Add Order Note for User
        if ($userNote) {
            $order->add_order_note($userNote, 1);
        }
        
        //Update Order Status
        if($newStatus != null) {
            if($this->isAllowedToChangeStatus($order)) {
                // here check what is defult status set by admin.
                $ntpSetting = get_option( 'woocommerce_netopiapayments_settings', [] );
                if( $order->has_downloadable_item() ) {
                    $newStatus = self::STATUS_WC_COMPLETED;
                } else {
                    switch ($ntpSetting['default_status']) {
                        case 'completed':
                            $newStatus = self::STATUS_WC_COMPLETED;
                            break;
                        case 'processing':
                            $newStatus = self::STATUS_WC_PROCESSING;
                            break;
                    }
                }

                if($customMsgStatus != null) {
                    // $order->add_order_note($customMsgStatus);
                    $order->update_status($newStatus,$customMsgStatus);
                } else {
                    $order->update_status($newStatus);
                }
            }
        }
    }

    /**
	 * Check if order status is allowed to be changed
	 */
	public function isAllowedToChangeStatus($orderInfo) {
		$arrStatus = array("completed", "processing");
		if (in_array($orderInfo->get_status(), $arrStatus)) {
			return false;
		}else {
			return true;
		}	
	}
}