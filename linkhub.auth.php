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
class Linkhub 
{
	const VERSION = '1.0';
	const ServiceURL = 'https://auth.linkhub.co.kr';
	private $__LinkID;
	private $__SecretKey;
	private $__requestMode = LINKHUB_COMM_MODE;
	
	private static $singleton = null;
	public static function getInstance($LinkID,$secretKey)
	{
		if(is_null(Linkhub::$singleton)) {
			Linkhub::$singleton = new Linkhub();
		}
		Linkhub::$singleton->__LinkID = $LinkID;
		Linkhub::$singleton->__SecretKey = $secretKey;
		
		return Linkhub::$singleton;
	}
	
	public function gzdecode($data){ $g=tempnam('/tmp','ff'); @file_put_contents($g,$data); ob_start(); readgzfile($g); $d=ob_get_clean(); unlink($g); return $d; }

	private function executeCURL($url,$header = array(),$isPost = false, $postdata = null) {
		if($this->__requestMode != "STREAM") {
			$http = curl_init($url);
		
			if($isPost) {
				curl_setopt($http, CURLOPT_POST,1);
				curl_setopt($http, CURLOPT_POSTFIELDS, $postdata);
			}
			curl_setopt($http, CURLOPT_HTTPHEADER,$header);
			curl_setopt($http, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($http, CURLOPT_ENCODING, 'gzip,deflate');
		
			$responseJson = curl_exec($http);
			
			$http_status = curl_getinfo($http, CURLINFO_HTTP_CODE);

			curl_close($http);

		
			if($http_status != 200) {
				throw new LinkhubException($responseJson);
			}
			return json_decode($responseJson);
		}
		else {
			if($isPost) {
				$params = array('http' => array(
					 'ignore_errors' => TRUE,
   	          		 'method' => 'POST',
					 'protocol_version' => '1.1',
    	         	 'content' => $postdata
        		    ));
	        } else {
	        	$params = array('http' => array(
 	  	     		 'ignore_errors' => TRUE,
					'protocol_version' => '1.1',
    	         	 'method' => 'GET'
        		    ));
	        }

  			if ($header !== null) {
		  		$head = "";
		  		foreach($header as $h) {
	  				$head = $head . $h . "\r\n";
	    		}
	    		$params['http']['header'] = substr($head,0,-2);
	  		}
	  		$ctx = stream_context_create($params);
			$response = $this->gzdecode(file_get_contents($url, false, $ctx));
	  		if ($http_response_header[0] != "HTTP/1.1 200 OK") {
	    		throw new LinkhubException($response);
	  		}
			return json_decode($response);
		}
	}
	
	public function getTime()
	{
		if($this->__requestMode != "STREAM") {
			$http = curl_init(Linkhub::ServiceURL.'/Time');
		
			curl_setopt($http, CURLOPT_RETURNTRANSFER, TRUE);
	
			$response = curl_exec($http);
		
			$http_status = curl_getinfo($http, CURLINFO_HTTP_CODE);

			curl_close($http);
			
			
			if($http_status != 200) {
				throw new LinkhubException($response);
			}
			return $response;
		
		} else { 
			$header = array();
			$header[] = 'Connection: close';
			$params = array('http' => array(
				 'ignore_errors' => TRUE,
        		'protocol_version' => '1.1',
				 'method' => 'GET'
   		    ));
			if ($header !== null) {
		  		$head = "";
		  		foreach($header as $h) {
	  				$head = $head . $h . "\r\n";
	    		}
	    		$params['http']['header'] = substr($head,0,-2);
	  		}
			
			
	  		$ctx = stream_context_create($params);

	  		$response = $this->gzdecode(file_get_contents(LInkhub::ServiceURL.'/Time', false, $ctx));

			if ($http_response_header[0] != "HTTP/1.1 200 OK") {
	    		throw new LinkhubException($response);
	  		}
			return $response;
		}
	}
	
	public function getToken($ServiceID, $access_id, array $scope = array() , $forwardIP = null)
	{
		$xDate = $this->getTime();
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
		
		$header[] = 'Authorization: LINKHUB '.$this->__LinkID.' '.$digest;
		$header[] = 'Content-Type: Application/json';
		$header[] = 'Connection: close';
				
		return $this->executeCURL(Linkhub::ServiceURL.$uri , $header,true,$postdata);
		
	}
  
		
	public function getBalance($bearerToken, $ServiceID)
	{
		$header = array();
		$header[] = 'Authorization: Bearer '.$bearerToken;	
		$header[] = 'Connection: close';
		$uri = '/'.$ServiceID.'/Point';

		$response = $this->executeCURL(Linkhub::ServiceURL . $uri,$header);

		return $response->remainPoint;
		
		
	}
	
	public function getPartnerBalance($bearerToken, $ServiceID)
	{
		$header = array();
		$header[] = 'Authorization: Bearer '.$bearerToken;
		$header[] = 'Connection: close';
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

class LinkhubException extends Exception
{
	public function __construct($response, Exception $previous = null) {
       $Err = json_decode($response);
       if(is_null($Err)) {
       		parent::__construct($response, -99999999);
       }
       else {
       		parent::__construct($Err->message, $Err->code);
       }
    }

    public function __toString() {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }

}

?>
