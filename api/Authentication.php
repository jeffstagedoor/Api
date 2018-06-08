<?php
/**
*	Class Authentication
*
*	@author Jeff Frohner
*	@copyright 2015
*	@version 1.0
*
**/

namespace Jeff\Api;

require_once("Environment.php");

header("Access-Control-Allow-Origin: ".Environment::$allowOrigin);
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

require_once(Environment::$dirs->vendor.'joshcam/mysqli-database-class/MysqliDb.php');

require_once("ErrorHandler.php");
require_once("Log/Log.php");
require_once("DataMasker.php");
require_once("ApiHelper.php");
require_once("Model.php");
require_once("Account.php");
#include_once("debughelpers.php");


/**
*	Class Authentication
*
*	@author Jeff Frohner
*	@copyright 2017
*	@version 1.2
*
**/
Class Authentication {
	/** @var \MySqlDb  Instance of Database Class */
	private $db;
	/** @var Models\Account Instance of ErrorHandler */
	private $account;

	/**
	* The Constructor
	*
	* sets up db connection {@see https://github.com/ThingEngineer/PHP-MySQLi-Database-Class}, 
	* instanciates the class {@see Models\Account}
	* @see Models\Account
	*/
	public function __construct() {
		// instatiate all nesseccary classes
		$this->db = new \MysqliDb(Environment::$database);
		$this->account = new Models\Account($this->db, null);
		// check if we have a database ready:
		try {
			$this->db->connect();
		} catch(\Exception $e) {
			$this->db = NULL;
			ErrorHandler::throwOne(Array("DB Error", "Could not connect to database", 500, true, ErrorHandler::CRITICAL_ALL));
			exit;
		}
	}


	/**
	* Authenticates a user.
	* Tries to find relevant authentication information in reuqest-header or POST-Object
	* Verifies the credentials found are valid
	* Then calls `authenticate()` method of account class to do the actual authentication.
	* If successfull it will return and echo a json:
	*
	* ```
	* { 	"access_token": "123456789",
	*		"refresh_token": "987654321",
	*		"token_type": "1",
	*		"expires_in": "604800",
	*		"account_id": '1'
	* }
	* ```
	*
	* @see Models\Account
	*
	*/
	public function authenticate() {
		#echo "thats a mess here in Authentication.php authenticate - working on revoke";
		$postObject = (Object) $_POST;
		$request = ApiHelper::getRequest();
		if($request[0]==='revoke') {
			echo "I am revoking!!";
		}
		if(isset($postObject->grant_type) && $postObject->grant_type==='refresh_token') {
			$auth = $this->account->refreshToken($postObject->refresh_token);
		} else {
			// normal authentification
			$identification = isset($postObject->username) ? $postObject->username : NULL;
			if(!$identification) { 
				$identification = isset($postObject->identification) ? $postObject->identification : NULL; 
			}
			$password = isset($postObject->password) ? $postObject->password : NULL;
			if(!$password) { 
				$password = isset($postObject->pwd) ? $postObject->pwd : NULL; 
			}
			// verify we have all we need
			if(is_null($identification) || is_null($password) || strlen($identification)<5 || strlen($password)<4) {
				ErrorHandler::throwOne(ErrorHandler::AUTH_CREDENTIALS_TOO_SHORT);
				exit;
			} else {
				// finally do the real authentication in account-class
				$auth = $this->account->authenticate($identification, $password);
			}
		}
		// check if it was successfull
		if(!$auth) {
			ErrorHandler::sendApiErrors();
			ErrorHandler::sendErrors();
			exit;
		} else {
			$json = '{ "access_token": "'.$auth->authToken.'",
				"refresh_token": "'.$auth->refreshToken.'",
				"token_type": "'.Environment::$authenticationConfig['tokenType'].'",
				"expires_in": "'.Environment::$authenticationConfig['authTokenExpiresIn'].'",
				"account_id": '.$auth->account_id.'
			}';
			header("Access-Control-Allow-Origin: ".Environment::$urls->allowOrigin);
			header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
			header("Access-Control-Allow-Headers: Content-Type");
			header("HTTP/1.0 200 OK");
			header('Content-Type: application/json; charset=UTF-8');
			echo $json;
			exit;
		}
	}
}