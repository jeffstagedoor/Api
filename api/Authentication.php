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

header("Access-Control-Allow-Origin: ".$ENV->urls->allowOrigin);
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

require($ENV->dirs->vendor.'joshcam/mysqli-database-class/MysqliDb.php');

require("ErrorHandler.php");
require("Log/Log.php");
require("DataMasker.php");
require("ApiHelper.php");
require("Model.php");
require("Account.php");
include_once("debughelpers.php");


/**
*	Class Authentication
*
*	@author Jeff Frohner
*	@copyright 2015
*	@version 1.0
*
**/
Class Authentication {
	/** @var Environment The current Environment Object, which holds infos about db-credentials, local paths, etc */
	private $ENV;
	/** @var \MySqlDb  Instance of Database Class */
	private $db;
	/** @var ErrorHandler Instance of ErrorHandler */
	private $errorHandler;
	/** @var Log Instance of ErrorHandler */
	private $log;
	/** @var Models\Account Instance of ErrorHandler */
	private $account;

	/**
	* The Constructor
	*
	* sets up db connection {@see https://github.com/ThingEngineer/PHP-MySQLi-Database-Class}, 
	* instanciates the classes {@see ErrorHandler}, {@see Log\Log}, {@see Models\Account}
	* @see Environment
	* @see ErrorHandler
	* @see Log\Log
	* @see Models\Account
	*
	* @param Environment $ENV
	*/
	public function __construct($ENV=null) {
		// echo "in Auth";
		$this->ENV = $ENV;
		// instatiate all nesseccary classes
		$this->errorHandler = new ErrorHandler();
		$this->db = new \MysqliDb($this->ENV->database);
		$this->log = new Log\Log($this->db, $this->ENV, $this->errorHandler);
		$this->account = new Models\Account($this->db, $this->ENV, $this->errorHandler, null);
		// check if we have a database ready:
		try {
			$this->db->connect();
		} catch(\Exception $e) {
			$this->db = NULL;
			$this->errorHandler->throwOne(Array("DB Error", "Could not connect to database", 500, true, ErrorHandler::CRITICAL_ALL));
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
			if(!$identification) { $identification = isset($postObject->identification) ? $postObject->identification : NULL; }
			$password = isset($postObject->password) ? $postObject->password : NULL;
			if(!$password) { $password = isset($postObject->pwd) ? $postObject->pwd : NULL; }

			if(strlen($identification)<5 || strlen($password)<4 || is_null($identification) || is_null($password)) {
				$this->errorHandler->throwOne(ErrorHandler::AUTH_CREDENTIALS_TOO_SHORT);
				exit;
			} else {
				$auth = $this->account->authenticate($identification, $password);
			}
		}
		if(!$auth) {
			$this->errorHandler->sendApiErrors();
			$this->errorHandler->sendErrors();
			exit;
		} else {
			$json = '{ "access_token": "'.$auth->authToken.'",
				"refresh_token": "'.$auth->refreshToken.'",
				"token_type": "1",
				"expires_in": "604800",
				"account_id": '.$auth->account_id.'
			}';
			header("Access-Control-Allow-Origin: ".$this->ENV->urls->allowOrigin);
			header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
			header("Access-Control-Allow-Headers: Content-Type");
			header("HTTP/1.0 200 OK");
			header('Content-Type: application/json; charset=UTF-8');
			echo $json;
			exit;
		}
	}
}