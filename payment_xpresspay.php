<?php
/**
 * @plugin 
 * @author Xpress Payment Solution
 * @copyright (C) Xpress Payment Solution
 * @license GNU/GPL http://www.gnu.org/copyleft/gpl.html
**/

defined('_JEXEC') or die('Restricted access');
require_once (JPATH_ADMINISTRATOR.'/components/com_j2store/library/plugins/payment.php');
// import the JPlugin class
jimport('joomla.event.plugin');
use Joomla\CMS\Log\Log;

// Add a prefix to this class name to avoid conflict with other plugins

class plgJ2StorePayment_xpresspay extends J2StorePaymentPlugin
{
var $_element = 'payment_xpresspay';
var $public_key;
var $plugin_name;	
protected static $tax_percentage;
protected $autoloadLanguage = true;
	public function __construct(&$subject, $config = array())
    {
        parent::__construct($subject, $config);

        $this->loadLanguage();
	}
     
	function _renderForm( $data )
	{
		$user = JFactory::getUser();
	     $pmntinfo = $this->getExpressPaymentInfo();
		 $public_key = $pmntinfo->public_key;
		if (empty($public_key)) {
			echo JText::_('No Public Key Found');
			return;
		}
		if (!isset($user)) {
			echo JText::_('User Must Be logged in');
			return;
		}

		
        $vars = new JObject();
        $vars->onselection_text = 'You have selected xpress payment solution has payment method. You will be redirected to a secure page to make payment.';
        //if this is a direct integration, the form layout should have the credit card form fields.
        $html = $this->_getLayout('form', $vars);
        return $html;
		
	}

	function _prePayment($data)
    {
        // get component params
        $params = J2Store::config();
        $currency = J2Store::currency();
		$app = JFactory::getApplication();

		JFactory::getApplication()->enqueueMessage('Some debug string(s)');
        //echo JText::_("The currency is". $currency);
		
        // prepare the payment form
		$pmntinfo = $this->getExpressPaymentInfo();
		$public_key = $pmntinfo->public_key;
		$enablecharge = $pmntinfo->ps_extra;
		$url = $pmntinfo->Url;
        $vars = new JObject();
        $vars->order_id = $data['order_id'];
        $vars->orderpayment_id = $data['orderpayment_id'];
        F0FTable::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_j2store/tables');
        $order = F0FTable::getInstance('Order', 'J2StoreTable')->getClone();
        $order->load(array('order_id' => $data['order_id']));
        $currency_values = $this->getCurrency($order);
        $vars->currency_code = $currency_values['currency_code'];

		Log::add($currency_values['currency_code'], Log::ERROR, "Currency");
		if($currency_values['currency_code']  != "NGN"){
			echo JText::_("<p style='color:red;text-align:center'>Invalid Currency Code</p>");
			return;
		}
        $vars->orderpayment_amount = $currency->format($order->order_total, $currency_values['currency_code'], $currency_values['currency_value'], false);
                //$orderinfo = $order->getOrderInformation();
        $vars->invoice = $order->getInvoiceNumber();
        //Log::add($vars->invoice, Log::ERROR, 'Invoice Response');
		$vars->orderpayment_id = $data['orderpayment_id'];
		$vars->orderpayment_type = $this->_element;

		F0FTable::addIncludePath(JPATH_ADMINISTRATOR.'/components/com_j2store/tables');
		$order = F0FTable::getInstance('Order', 'J2StoreTable');
		$order->load($data['orderpayment_id']);
        //$orderinfo = $order->getOrderInformation();
        $return_url = $rootURL . JRoute::_("index.php?option=com_j2store&view=checkout&task=confirmPayment&orderpayment_type=" . $this->_element . "&paction=display");
        $cancel_url = $rootURL . JRoute::_("index.php?option=com_j2store&view=checkout&task=confirmPayment&orderpayment_type=" . $this->_element . "&paction=cancel");
        $callback_url = JURI::root() . "index.php?option=com_j2store&view=checkout&task=confirmPayment&orderpayment_type=" . $this->_element . "&paction=callback&tmpl=component&orderid=". $order->order_id;

		$currency_values= $this->getCurrency($order);
		$amount = J2Store::currency()->format($order->order_total, $currency_values['currency_code'], $currency_values['currency_value'], false);
        
		$vars->display_name = $this->params->get('display_name', 'Xpress Payment Solution');
		$vars->onbeforepayment_text = $this->params->get('onbeforepayment', '');
		$vars->button_text = $this->params->get('button_text', 'J2STORE_PLACE_ORDER');
	    $vars->action = "postpayment.php";
		$vars->display_image = "/plugins/j2store/payment_xpresspay/images/xpresslogo.png";
		Log::add(json_encode($order) , Log::ERROR, 'Result Response from xpress payment 1');
		Log::add($order->order_id , Log::ERROR, 'Result Response from xpress payment 1');
        $resp = $this->process_redirect_payment($order->user_email ,  $amount ,  $order->order_id, $order->currency_code , $public_key,$callback_url , $enablecharge , $url);
        extract($resp);
		//Log::add($redirect, Log::ERROR, 'redirect Response from xpress payment 2');
		//Log::add($result, Log::ERROR, 'redirect Response from xpress payment 3');
		$vars->redirect = $redirect;
        if($result === "success"){
			$html = $this->_getLayout('prepayment', $vars);
			return $html;
		}else{
			echo JText::_("<p style='color:red;text-align:center'>An error occured.Kindly try again.</p>");
		}
        //Log::add(json_encode($resp), Log::ERROR, 'redirect Response from xpress payment 2');
		
        

		
     
    }

	function _postPayment($data)
    {
        
    // Process the payment
     $app = JFactory::getApplication();
     $paction = $app->input->getString('paction');
     $orderid = $app->input->getString('orderid');
      $vars = new JObject();

     switch ($paction)
     {
       case "display":
       $vars->message = 'Thank you for the order.';
       $html = $this->_getLayout('message', $vars);
 // Get the thank you message from the article (ID) provided in the plugin params
       $html .= $this->_displayArticle();
       break;
 
 	   case "callback":
 // It is a call back. You can update the order based on the response from the payment gateway

 		//'Redirect to the payment gateway';

 // Process the response from the gateway
 		//$this->_verifyPayment();
	    $resp = $this->VerifyPayment($orderid);
		// $resp = '00';
		$vars->status = $resp ;
		$vars->order_id = $orderid;
		$vars->message = $resp == "00" ? "Order payment completed successfully" : "Order payment failed";
 		$html = $this->_getLayout('message', $vars);
 		//echo $html; 
 		$html .= $this->_displayArticle();
		 break;
 
 		case "cancel":
 // Cancel is called. 
 		$vars->message = 'Sorry, you have cancelled the order';
 		$html = $this->_getLayout('message', $vars);
 		break;
 
 		default:
 		$vars->message = 'Seems an unknow request.';
 		$html = $this->_getLayout('message', $vars);
		 break;
 }

		return $html;
    }
     

	public function process_redirect_payment($email , $amount , $order_id , $currency , $public_key , $callback_url , $enablecharge , $url)
	{
		$app = JFactory::getApplication();
		$txnref       = $order_id . '' . time();
	    $currency = "NGN";
		$applyCharges= $enablecharge == 0 ? false : true;//true;
		
		$xpresspay_params = array(
			'email'             => $email,
			'amount'            => $amount,
			'transactionId'     => $txnref,			
			'currency'          => $currency,			
			'callbackUrl'      => $callback_url,
			'ApplyConviniencyCharge' => $applyCharges,
		);    
		//$xpresspay_url = 'http://172.22.54.111:2804/api/Payments/Initialize/';
		$xpresspay_url = $url.'Initialize/';
		//Log::add(json_encode($xpresspay_params), Log::ERROR, "Request log");
		//Log::add(http_build_query($xpresspay_params), Log::ERROR, "Request log 2");
		
		$ch = curl_init();
		$authorization  = "Authorization : Bearer " . $public_key;
		curl_setopt($ch, CURLOPT_URL,$xpresspay_url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_POST, 1 );
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , $authorization ));
		curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($xpresspay_params));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$server_output = curl_exec($ch);
		$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close ($ch);

		if ($httpcode == 200) { 
			$resp = json_decode($server_output);
            if ($resp->responseCode === '00') {
				$url = $resp->data->paymentUrl;
			          
				//$app = JFactory::getApplication();
  
				//echo "<script>document.location.href='$url';</script>\n";
				//$app->redirect($resp->data->paymentUrl, $msg='', $msgType='message', $moved=false);
				//header("Location:".$resp->data->paymentUrl,TRUE,307);
				//exit;
				//header("Access-Control-Allow-Origin: http://localhost:1113");
                Log::add($url, Log::ERROR, "Request URL");
				//header("Location: ".$resp->data->paymentUrl);
				return array(
					'result'   => 'success',
					'redirect' => $resp->data->paymentUrl,
				);
			}else{
				return array(
					'result'   => 'failed',
					'redirect' => '',
				);
			}
		} else {}
		
	}
    
    public function VerifyPayment($orderid){
		$pmntinfo = $this->getExpressPaymentInfo();
		$public_key = $pmntinfo->public_key;
		$url = $pddpinfo->Url;
		$xpresspay_url = $url.'VerifyPayment'; //'http://172.22.54.111:2804/api/Payments/VerifyPayment'; // . $xpresspay_txn_ref;
        $xpresspay_params = array(
			'transactionId'     => $orderid,			
		);    
        Log::add($xpresspay_url, Log::ERROR, "URL log");
		$ch = curl_init();
		$authorization  = "Authorization : Bearer " . $public_key;
		curl_setopt($ch, CURLOPT_URL,$xpresspay_url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_POST, 1 );
		curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($xpresspay_params));
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json','timeout : 60' , $authorization ));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$server_output = curl_exec($ch);
		$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close ($ch);
        Log::add(json_encode($server_output), Log::ERROR, "Request log");
        
		if ($httpcode == 200) { 
			$resp = json_decode($server_output);
			$this->_processSale($orderid , $resp->responseCode);
			return $resp->responseCode;
		}
    }

	public function _processSale($order_id , $respondCode)
    {
        //$app = JFactory::getApplication();
        //$data = $app->input->getArray($_POST);
        //get the order id sent by the gateway. This may differ based on the API of your payment gateway
        //$order_id = $data['YOUR_PAYMENT_GATEWAY_FIELD_HOLDING_ORDER_ID'];
        // load the orderpayment record and set some values
        $order = F0FTable::getInstance('Order', 'J2StoreTable')->getClone();
        if ($order->load(array('order_id' => $order_id))) {
            $order->add_history(JText::_('J2STORE_CALLBACK_RESPONSE_RECEIVED'));
            // save the data
            if (!$order->store()) {
                $errors[] = $order->getError();
            }
            //clear cart
			if($respondCode == "00"){
              $order->payment_complete();
              $order->empty_cart();
			}
			else{
				$order->order_state_id = 3;
				$order->order_state = JText::_('J2STORE_FAILED');
				$order->store();
			}
			
			
        }
        return count($errors) ? implode("\n", $errors) : '';
    }

	
	public function onGetDownloadLinkId($transactionId, &$download_id)
	{
		$session = JFactory::getSession();
		$transactions = $session->get("trans", array());
		if(isset($transactions[$transactionId]))
		{
			$download_id = $transactions[$transactionId]["download_id"];
		}
	}

	/*
	 * @param string $secret_key is either the demo or live secret key from your dashboard. $transactionid is the transaction reference code sent to the API previously
	 */
    public function getExpressPaymentInfo()
    {
    	$pddpinfo = new StdClass();
    	$pddpinfo->pmode = $this->params->get('ppdp_test_mode');//get test mode and use to get keys

    	switch($pddpinfo->pmode)
    	{
    		case 0: //its in test mode
    			//$pddpinfo->secret_key = trim($this->params->get('ppdp_test_secret_key'));
				$pddpinfo->Url = "http://172.22.54.111:2804/api/Payments/";
    			$pddpinfo->public_key = trim($this->params->get('ppdp_test_public_key')); break;
    		case 1://its live
    			//$pddpinfo->secret_key = trim($this->params->get('ppdp_live_secret_key'));
				$pddpinfo->Url = "http://172.22.54.111:2804/api/Payments/"; //Live URL
    			$pddpinfo->public_key = trim($this->params->get('ppdp_live_public_key')); break;
    		default: //its in test mode
    			//$pddpinfo->secret_key = trim($this->params->get('ppdp_test_secret_key'));
				$pddpinfo->Url = "http://172.22.54.111:2804/api/Payments/";
    			$pddpinfo->public_key = trim($this->params->get('ppdp_test_public_key')); break;
    	}
    	//$pddpinfo->notify_email = $this->params->get('notify_email', '');

    	$pddpinfo->ps_extra = $this->params->get('XPRESSPAY_extra_yes_no');
    	//$pddpinfo->ps_extratype = $this->params->get('XPRESSPAY_extra_type');
    	//$pddpinfo->ps_extraval = $this->params->get('XPRESSPAY_extra_charges_value');

    	//$pddpinfo->currency = $this->params['paystack_currcode'];
    	return $pddpinfo;
    }

    protected function _getTaxPercentage()
    {
    	if (!isset(self::$tax_percentage)) {

    		$db = JFactory::getDBO();

    		$db->setQuery('SELECT tax_rate FROM #__payperdownloadplus_config');

    		self::$tax_percentage = 0;
    		try {
    			$tax_percentage = $db->loadResult();
    			if (!is_null($tax_percentage)) {
    				self::$tax_percentage = $tax_percentage;
    			}
    		} catch (RuntimeException $e) {
    			self::$tax_percentage = 0;
    		}
    	}

    	return self::$tax_percentage;
    }

    protected function _loadTemplate($file = null, $variables = array())
    {
        $template = JFactory::getApplication()->getTemplate();
        $overridePath = JPATH_THEMES.'/'.$template.'/html/plg_j2store_payment_xpresspay';

        if (is_file($overridePath.'/'.$file)) {
            $file = $overridePath.'/'.$file;
        } else {
            $file = __DIR__.'/tmpl/'.$file;
        }

        unset($template);
        unset($overridePath);

        if (!empty($variables)) {
            foreach ($variables as $name => $value) {
                $$name = $value;
            }
        }

        unset($variables);
        unset($name);
        unset($value);
        if (isset($this->this)) {
            unset($this->this);
        }

        @ob_start();
        include $file;
        $html = ob_get_contents();
        @ob_end_clean();

        return $html;
    }

}

?>
