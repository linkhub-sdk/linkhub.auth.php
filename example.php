<?php

require_once 'linkhub.auth.php';
use Linkhub\Linkhub;
use Linkhub\Token;
use Linkhub\LinkhubException;

$ServiceID = 'POPBILL_TEST';
$PartnerID = 'TESTER';
$SecretKey = 'okH3G1/WZ3w1PMjHDLaWdcWIa/dbTX3eGuqMZ5AvnDE=';

$AccessID = '1231212312';
$Linkhub = Linkhub::newInstance($PartnerID,$SecretKey);

try
{
	$Token = $Linkhub->getToken($ServiceID,$AccessID, array('member','110'));
}catch(LinkhubException $le) {
	echo $le;
	
	exit();
}
echo 'Token is issued : '.substr($Token->session_token,0,20).' ...';
echo chr(10);

try
{
	$balance = $Linkhub->getBalance($Token->session_token,$ServiceID);
}catch(LinkhubException $le) {
	echo $le;
	
	exit();
}
echo 'remainPoint is '. $balance;
echo chr(10);

try
{
	$balance = $Linkhub->getPartnerBalance($Token->session_token,$ServiceID);
}catch(LinkhubException $le) {
	echo $le;
	
	exit();
}
echo 'remainPartnerPoint is '. $balance;
echo chr(10);

?>
