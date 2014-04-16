<?php
/**
* =====================================================================================
* Class for develop interoperation with Linkhub APIs.
* Functionalities are authentication for Linkhub api products, and to support
* several base infomation(ex. Remain point).
*
* This module uses curl and openssl for HTTPS Request. So related modules must
* be installed and enabled.
*
* http://www.linkhub.co.kr
* Author : Kim Seongjun (pallet027@gmail.com)
* Written : 2014-04-15
*
* Thanks for your interest.
* We welcome any suggestions, feedbacks, blames or anythings.
* ======================================================================================
*/

namespace Linkhub;

class Linkhub 
{
	const VERSION = '1.0';
	const ServiceURL = 'https://auth.linkhub.co.kr';
	private $__PartnerID;
	private $__SecretKey;
	
	private static $singleton = null;
	public static function getInstance($PartnerID,$secretKey)
	{
		if(is_null(Linkhub::$singleton)) {
			Linkhub::$singleton = new Linkhub();
		}
		Linkhub::$singleton->__PartnerID = $PartnerID;
		Linkhub::$singleton->__SecretKey = $secretKey;
		
		return Linkhub::$singleton;
	}
	
	private function executeCURL($url,$header = array(),$isPost = false, $postdata = null) {
		$http = curl_init($url);
		
		if($isPost) {
			curl_setopt($http, CURLOPT_POST,1);
			curl_setopt($http, CURLOPT_POSTFIELDS, $postdata);   
		}
		curl_setopt($http, CURLOPT_HTTPHEADER,$header);
		curl_setopt($http, CURLOPT_RETURNTRANSFER, TRUE);
		
		$responseJson = curl_exec($http);
		
		$http_status = curl_getinfo($http, CURLINFO_HTTP_CODE);
		
		curl_close($http);
			
		if($http_status != 200) {
			throw new LinkhubException($responseJson);
		}
		
		return json_decode($responseJson);
	}
	
	public function getToken($ServiceID, $access_id, array $scope = array() , $forwardIP = null)
	{
		date_default_timezone_set("UTC");
		$xDate = date("Y-m-d\TH:i:s\Z", time()); 
		
		$uri = '/' . $ServiceID . '/Token';
		$header = array();
		
		$TokenRequest = new TokenRequest();
		$TokenRequest->access_id = $access_id;
		$TokenRequest->scope = $scope;
		
		$postdata = json_encode($TokenRequest);
		
		$digestTarget = 'POST'.chr(10);
		$digestTarget = $digestTarget.base64_encode(md5($postdata,true)).chr(10);
		$digestTarget = $digestTarget.$xDate.chr(10);
		if(!(is_null($forwardIP) || $forwardIP == '')) {
			$digestTarget = $digestTarget.$forwardIP.chr(10);
		}
		$digestTarget = $digestTarget.Linkhub::VERSION.chr(10);
		$digestTarget = $digestTarget.$uri;
		
		$digest = base64_encode(hash_hmac('sha1',$digestTarget,base64_decode(strtr($this->__SecretKey, '-_', '+/')),true));
		
		$header[] = 'x-lh-date: '.$xDate;
		$header[] = 'x-lh-version: '.Linkhub::VERSION;
		if(!(is_null($forwardIP) || $forwardIP == '')) {
			$header[] = 'x-lh-forwarded: '.$forwardIP;
		}
		
		$header[] = 'Authorization: LINKHUB '.$this->__PartnerID.' '.$digest;
		$header[] = 'Content-Type: Application/json';
		
		return $this->executeCURL(Linkhub::ServiceURL.$uri , $header,true,$postdata);
		
	}
	
	
	public function getBalance($bearerToken, $ServiceID)
	{
		$header = array();
		$header[] = 'Authorization: Bearer '.$bearerToken;
		
		$uri = '/'.$ServiceID.'/Point';
		
		$response = $this->executeCURL(Linkhub::ServiceURL . $uri,$header);
		return $response->remainPoint;
		
	}
	
	public function getPartnerBalance($bearerToken, $ServiceID)
	{
		$header = array();
		$header[] = 'Authorization: Bearer '.$bearerToken;
		
		$uri = '/'.$ServiceID.'/PartnerPoint';
		
		$response = $this->executeCURL(Linkhub::ServiceURL . $uri,$header);
		return $response->remainPoint;
		
	}
}

class TokenRequest
{
	public $access_id;
	public $scope;
}

class LinkhubException extends \Exception
{
	public function __construct($response, Exception $previous = null) {
       $Err = json_decode($response);
       if(is_null($Err)) {
       		parent::__construct($response, -99999999, $previous);
       }
       else {
       		parent::__construct($Err->message, $Err->code, $previous);
       }
    }

    public function __toString() {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }

}

?>