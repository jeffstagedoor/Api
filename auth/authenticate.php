<?php
/**
*	Authentication-API
*
*	@author Jeff Frohner
*	@copyright 2015
*	@version 1.0
*
**/

namespace Jeff\Api;

require_once(__DIR__.'/../api/Account.php');
require_once(__DIR__.'/../api/Err.php');
require_once(__DIR__.'/../../../../vendor/MysqliDb.php');

$db = new \MysqliDb($ENV->database);
$err = new Err();

$Account = new Models\Account($db);

//vars auslesen - tries idenfication/username and password/pwd
$postObject = (Object) $_POST;

if(isset($postObject->grant_type) && $postObject->grant_type==='refresh_token') {
	$auth = $Account->refreshToken($postObject->refresh_token);

};



$identification = isset($postObject->username) ? $postObject->username : NULL;
if(!$identification) { $identification = isset($postObject->identification) ? $postObject->identification : NULL; }
$password = isset($postObject->password) ? $postObject->password : NULL;
if(!$password) { $password = isset($postObject->pwd) ? $postObject->pwd : NULL; }


if(strlen($identification)<5 || strlen($password)<4) {
	$errstr = '{"error":"invalid_request"}';
	header("Access-Control-Allow-Origin: ".$ENV->urls->allowOrigin);
	header("Access-Control-Allow-Methods: POST, OPTIONS");
	header("Access-Control-Allow-Headers: Content-Type");
	header("HTTP/1.0 400 Bad Request");
	header('Content-Type: application/json');
	echo $errstr;
	exit;
} else {

	$auth = $Account->authenticate($identification, $password);
	if($err->hasErrors()) {
		$errors = $err->get();
		header("Access-Control-Allow-Origin: ".$ENV->urls->allowOrigin);
		header("Access-Control-Allow-Methods: POST, OPTIONS");
		header("Access-Control-Allow-Headers: Content-Type");
		header("HTTP/1.0 401 Unauthorized");
		header('Content-Type: application/json');
		echo '{"errors": '.json_encode($errors). '}';
		exit;
	} else {
		$json = '{
			"access_token": "'.$auth->authToken.'",
			"token_type": "1",
			"expires_in": "604800",
			"account_id": '.$auth->account_id.'
		}';
		header("Access-Control-Allow-Origin: ".$ENV->urls->allowOrigin);
		header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
		header("Access-Control-Allow-Headers: Content-Type");
		header("HTTP/1.0 200 OK");
		header('Content-Type: application/json; charset=UTF-8');
		echo $json;
		exit;
	}

}