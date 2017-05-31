<?php
#########################
#
# api.php
#
# REST API
#
# copy Jeff Frohner 2017
#
# Version 1.3
#
#########################

namespace Jeff\Api;
// use Jeff\Api\Models;



require("../vendor/MysqliDb.php");
// require("../vendor/Jeff/Api/api/Err.php");

require("Err.php");
require("Log.php");
require("DataMasker.php");
require("ApiHelper.php");
require("Model.php");
include("Account.php");

$err = new Err();
$db = new \MysqliDb($ENV->database);
$log = new Log($db);
$Account = new Models\Account($db);


// developing options. MUST be false for production
$NOAUTH = true;
// $showExecutionTime = false;


// put together what was passed as parameters to this api:
$method = $_SERVER['REQUEST_METHOD'];
$request = ApiHelper::getRequest();
$data = ApiHelper::getData();


// User-Authentication and Authorization
// authenticate current user

if( $request[0]==='login' || 
	$request[0]==='signup' || 
	$request[0]==='getimage' ||
	($request[0]==='tasks' && isset($request[1]) && $request[1]==='user2artistconfirmation') || 
	$NOAUTH===true) { 
	// no need for authentication if user wants to login or signin

} else {
	// check if and where we got an authToken
	$headers = getallheaders();
	if(isset($headers['Authorization'])) {
		$auth = explode(" ", $headers['Authorization']);
		$authToken = $auth[1];
		$authType = $auth[0];
	} elseif (isset($data->authToken)) {
		$authToken = $data->authToken;
	} else {	// no authtoken found -> send error & exit script!
		$authToken = null;
		$response = "{\"errors\": [{\"msg\": \"no authToken found\", \"code\": 90}] }";
		ApiHelper::sendResponse(401, $response);
		exit;
	}
	$success = $Account->reAuthenticate($authToken);

	if(!$Account->isAuthenticated) {	
		// authorization failed
		$response  = "{\"errors\": [{\"msg\": \"could not authenticate user\", \"code\": 91}] }";
		ApiHelper::sendResponse(401, $response);
		exit;
	} else { 
		// authorization succeeded
		$Account->updateLastOnline();
	}
}

if($NOAUTH==true) {
	// mock an account just for developing
	$Account->mockAccount();
}
// END User-Authentication

header("Access-Control-Allow-Origin: ".$ENV->urls->allowOrigin);
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Origin, Content-Type, Authorization, X-Custom-Auth");


switch ($method) {
  case 'PUT':
  	include('restPut.php');
	rest_put($request, $data);  
	break;
  case 'POST':
  	include('restPost.php');
	rest_post($request, $data);  
	break;
  case 'GET':
  	include('restGet.php');
	rest_get($request, $data);
	// possible new Version, a not yet fully functional port to a proper class. No new functions/properties.
  	#include('RestGet.class.php');
	#$rest = new RestGet($db, $request, $data);
	break;
  case 'HEAD':
	rest_head($request, $data);  
	break;
  case 'DELETE':
  	include('restDelete.php');
	rest_delete($request, $data);  
	break;
  case 'OPTIONS':
	rest_options($request, $data);    
	break;
  default:
	rest_error($request, $data);  
	break;
}



function rest_head($request) {

}
function rest_options($request) {

}
function rest_error($request) {

}