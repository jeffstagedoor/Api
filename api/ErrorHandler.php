<?php
/**
*	Class Err
*	
*	
*	@author Jeff Frohner
*	@copyright Copyright (c) 2015-2017
*	@license   private
*	@version   1.0
*
**/

namespace Jeff\Api;


Class ErrorHandler {
	public $testVal = "original from class";
	
	Const DB_ERROR = 					20;
	Const DB_NOT_FOUND = 				21;
	Const DB_INSERT = 					22;
	Const DB_UPDATE = 					23;
	Const DB_DELETE = 					24;

	Const MODEL_NOT_DEFINED = 			30;
	Const MODEL_NOT_ALLOWED = 			31;
	Const MODEL_NOT_SORTABLE = 			33;
	Const MODEL_SORT_OOR = 				34;
	Const MODEL_TABLE_MISSING = 		35;


	Const API_INVALID_REQUEST = 		40;
	Const API_INVALID_POST_REQUEST = 	41;
	Const API_INVALID_GET_REQUEST = 	42;
	Const API_INVALID_PUT_REQUEST = 	43;
	Const API_INVALID_POSTPUT_REQUEST = 44;
	Const API_ID_MISSING = 				45;

	Const LOG_NO_CONFIG = 				50;
	Const LOG_NO_TABLE = 				51;


	Const AUTH_NO_AUTHTOKEN =		 	90;
	Const AUTH_FAILED =				 	91;
	Const AUTH_PWD_INCORRECT =		 	92;
	Const AUTH_USER_UNKNOWN =		 	93;

	Const AUTH_PWD_NOT_MATCHING =		97;
	Const AUTH_INT_ACCOUNTNOTSET =		99;
	Const CUSTOM =						100;


	Const CRITICAL_LOG = 1;
	Const CRITICAL_EMAIL = 2;
	Const CRITICAL_ALL = 3;


	private $Errors = Array();
	public static $Codes = Array(

	// DB
		20 => Array("title"=>"Database Error", "msg"=>"An undefined database error occured.", "httpCode"=>500,	"critical"=>self::CRITICAL_LOG),
		21 => Array("title"=>"Database Error", "msg"=>"Could not find requested resource.", "httpCode"=>404,	"critical"=>self::CRITICAL_LOG),
		22 => Array("title"=>"Database Error", "msg"=>"Could not insert record.", "httpCode"=>400,				"critical"=>self::CRITICAL_EMAIL),
		23 => Array("title"=>"Database Error", "msg"=>"Could not update record.", "httpCode"=>400,				"critical"=>self::CRITICAL_EMAIL),
		24 => Array("title"=>"Database Error", "msg"=>"Could not delete record.", "httpCode"=>400,				"critical"=>self::CRITICAL_EMAIL),

	// MODELS
		30 => Array("title"=>"Model Error", "msg"=>"This Model is not defined", "httpCode"=>400,										"critical"=>self::CRITICAL_ALL),
		33 => Array("title"=>"Model Error", "msg"=>"Trying to sort an item, \nthat is not defined as sortable.", "httpCode"=>400,				"critical"=>self::CRITICAL_EMAIL),
		34 => Array("title"=>"Model Error", "msg"=>"Trying to sort an item, \nbut that item is already first/last of group.", "httpCode"=>400,	"critical"=>self::CRITICAL_EMAIL),
		35 => Array("title"=>"Model Error", "msg"=>"The Database Table for this Model does not exist", "httpCode"=>400,							"critical"=>self::CRITICAL_ALL),

	// API
		40 => Array("title"=>"Invalid API request", "msg"=>"Invalid API request", "httpCode"=>400, 			"critical"=>self::CRITICAL_LOG),
		41 => Array("title"=>"Invalid post request", "msg"=>"Invalid post request", "httpCode"=>400, 		"critical"=>self::CRITICAL_LOG),
		42 => Array("title"=>"Invalid get request", "msg"=>"Invalid get request", "httpCode"=>400, 			"critical"=>self::CRITICAL_LOG),
		43 => Array("title"=>"Invalid put request", "msg"=>"Invalid put request", "httpCode"=>400, 			"critical"=>self::CRITICAL_LOG),
		44 => Array("title"=>"Invalid post/put request", "msg"=>"Not all required fields received", "httpCode"=>400, "critical"=>self::CRITICAL_LOG),
		44 => Array("title"=>"Invalid post/put request", "msg"=>"Recource id is missing", "httpCode"=>400, "critical"=>self::CRITICAL_LOG),

	// LOG
		50 => Array("title"=>"Log Error", "msg"=>"No Log Config found", "httpCode"=>500,	"critical"=>self::CRITICAL_EMAIL, "internal"=>true),
		51 => Array("title"=>"Log DB Error", "msg"=>"Log Table not found", "httpCode"=>500, 	"critical"=>self::CRITICAL_EMAIL, "internal"=>true),


	// Authorization
		90 => Array("title"=>"No AuthToken found", "msg"=>"Could not find a valid authorization token.", "httpCode"=>401,	"critical"=>self::CRITICAL_LOG),
		91 => Array("title"=>"Authentication failed", "msg"=>"Could not authenticate user.", "httpCode"=>401,				"critical"=>self::CRITICAL_LOG),
		92 => Array("title"=>"Incorrect Password", "msg"=>"Password is not correct.", "httpCode"=>401,						"critical"=>self::CRITICAL_LOG),
		93 => Array("title"=>"Unknown User", "msg"=>"Could not find a user with these credentials.", "httpCode"=>401,		"critical"=>self::CRITICAL_LOG),

		97 => Array("title"=>"Not matching Passwords", "msg"=>"The passwords do not match.", "httpCode"=>401,			"critical"=>0),

		99 => Array("title"=>"Internal Error", "msg"=>"Account not set", "httpCode"=>500, 	"critical"=>self::CRITICAL_ALL),
		100 => Array("title"=>"Custom", "msg"=>"Custom", "httpCode"=>500, "critical"=>self::CRITICAL_EMAIL),
	);


	public function __construct() {

	}


	/**
	*	add
	*	adds an Error to the error array
	*	@param [int] error code, [array] title and msg for custom errors
	*	@return [array] all errors
	**/
	public function add($e) {
		if(is_integer($e)) {
			// if I get an Integer, it's the number-code of a predefined error
			// so lets make this a real Error Instance
			$this->Errors[] = new Error($e);
		} elseif(is_array($e)) {
			// if I get an Array, it's a custom error in format ['title', 'msg']
			$this->Errors[] = new Error($e);

		} elseif($e instanceof Error) {
			// if I get an Instance of Error-Class, it's the best anyway
			$this->Errors[] = $e;

		} else {
			$this->add(Array("Error in Class Err:"," e is not an Instance of Error, nor an Integer, nor an Array.".var_export($e, true), 500, 1));
		}
		// return all saved errors by defult, as a shortcut
		return $this->get();
	}


	/**
	*	throwOne
	*	adds an Error to the error array and sends the errors to client and log (and email if spezified)
	*	@param [int] error code, [array] title and msg for custom errors
	*	@return [array] all errors
	**/
	public function throwOne($e) {
		$this->add($e);
		$this->sendApiErrors();
		$this->sendErrors();
	}


	// returns ALL saved Errors
	public function get() {
		$arr = Array();
		foreach ($this->Errors as $key => $error) {
			$arr[] = $error;
		}
		return $arr;
	}

	// returns all public Errors as array
	public function getPublic() {
		$arr = Array();
		foreach ($this->Errors as $key => $error) {
			if($error->isPublic()) {
			#	echo "isPublic:";
			#	var_dump($error);
				$arr[] = $error->toArray();
			}
		}
		return $arr;
	}

	public function hasErrors() {
		if(sizeof($this->Errors)>0) {
			return true;
		} else {
			return false;
		}
	}

	public function sendApiErrors() {
		$errors = $this->getPublic();
		if(count($errors)) {
			// var_dump($errors);
			http_response_code($errors[0]['httpCode']);
			header("Content-Type: application/json");
			echo '{"errors": '.json_encode($errors). '}';
		}
	}

	public function sendErrors() {
		global $logConfig;
		// echo "send Errors";
		foreach ($this->Errors as $key => $error) {
			$e = $error->toArray(true);
			$txt = date('d.m.Y H:i:s').": {$e['title']} - {$e['msg']} ".PHP_EOL;
			if(isset($e['stackTrace']) && $e['stackTrace']>'') {
				$txt .= "    ".$e['stackTrace'].PHP_EOL;
			}
		}
		$geoInfo = LogHelper::getGeoInfoArray();
		$txt.= "     ".$_SERVER['REMOTE_ADDR']." ".implode(", ",$geoInfo).PHP_EOL;
		$logPath = isset($logConfig) ? $logConfig->logPath : "../apiLog";
		if (!is_dir($logPath)) {
    		mkdir($logPath, 0664, true);
		}
		$logFileName = "ApiLog ".date('Ymd').".txt";
		$myfile = file_put_contents($logPath.DIRECTORY_SEPARATOR.$logFileName, $txt.PHP_EOL , FILE_APPEND | LOCK_EX);
		// depending on what errors we've got we either echo them as json, or write it to the log, or send an email.
	}


}

Class Error {
	private $httpCode;
	private $title;
	private $msg;
	private $internal = false;
	private $critical = 0;
	private $stackTrace;
	Const DEFAULT_INTERNAL = false;
	Const DEFAULT_CRITICAL = 0;
	Const DEFAULT_CODE = 500;



	public function __construct($e, $info=null) {
		if(is_integer($e)) {
			$err = ErrorHandler::$Codes[$e];
			// var_dump($err);
			$this->httpCode = $err['httpCode'];
			$this->title = $err['title'];
			$this->msg = $err['msg'];
			$this->stackTrace = $info;
			$this->critical = $err['critical'];
			$this->internal = isset($err['internal']) ? $err['internal'] : false;
		} elseif(is_array($e)) {
			// if I get an Array, it's a custom error in format ['title', 'msg', [bool] internal, [int] critical]
			$this->httpCode = isset($e[2]) ? $e[2] : self::DEFAULT_CODE;
			$this->title = $e[0];
			$this->msg = $e[1];
			$this->internal = isset($e[3]) ? $e[3] : self::DEFAULT_INTERNAL;
			$this->stackTrace = $info;
			$this->critical = isset($e[4]) ? $e[4] : self::DEFAULT_CRITICAL;
		} elseif (is_string($e)) {
			// if I get only ONE String, try to make the best out of it
			$this->httpCode = 500;
			$this->title = "Custom Error";
			$this->msg = $e;
			$this->internal = true;
			$this->stackTrace = $info;
		}
	}

	public function toArray($internal=false) {
		$a = Array(
			"title"=> $this->title,
			"msg"=> $this->msg,
			"httpCode"=> $this->httpCode,

			);
		if($internal) {
			$a["internal"]= $this->internal;
			$a["critical"]= $this->critical;
			$a["stackTrace"]= $this->stackTrace;
			
		}

		return $a;
	}

	public function isPublic() {
		if($this->internal) {
			return false;
		} else {
			return true;
		}
	}
}
