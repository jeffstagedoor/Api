<?php
#########################
#
# Api.class.php
#
# REST API
#
# copy Jeff Frohner 2017
#
# Version 1.3.1
#
#########################

namespace Jeff\Api;
// use Jeff\Api\Models;
header("Access-Control-Allow-Origin: ".$ENV->urls->allowOrigin);
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

require($ENV->dirs->vendor.'joshcam/mysqli-database-class/MysqliDb.php');

require("ErrorHandler.php");
require("Log.php");
require("DataMasker.php");
require("ApiHelper.php");
require("Model.php");
require("Account.php");

Class ApiInfo {
	public static $version = "1.3.1";
	public static $author = "Jeff Frohner";
	public static $year = "2017";
	public static $licence = "MIT";
	public static $type = "REST";
	public static $restriction = "authorized apps and logged in users only";

	/**
	*	getApiInfo
	*	
	*	@param [string] format ('array', 'json'=default)
	*	@return [array]
	**/
	public static function getApiInfo($format='json') {
		$array = Array(
			"version"=>self::$version,
			"author"=>self::$author,
			"year"=>self::$year,
			"licence"=>self::$licence,
			"type"=>self::$type,
			"restriction"=>self::$restriction
			);
		return json_encode($array);
	}

}


Class Api {

	private $ENV;
	private $NOAUTH=false;
	private $specialVerbs = Array('meta', 'login', 'signup', 'signin', 'task', 'sort', 'search', 'count', 'apiInfo', 'getFile', 'getImage','getFolder');
	private $models;
	private $request;
	private $data;
	private $log;

	Const REQUEST_TYPE_NORMAL = 1;
	Const REQUEST_TYPE_REFERENCE = 2;
	Const REQUEST_TYPE_COALESCE = 3;
	Const REQUEST_TYPE_QUERY = 4;
	Const REQUEST_TYPE_SPECIAL = 5;
	Const REQUEST_TYPE_INFO = 6;

	


	public function __construct($ENV=null) {
		
		$this->ENV = $ENV;
		// instatiate all nesseccary classes
		$this->errorHandler = new ErrorHandler();
		$this->db = new \MysqliDb($this->ENV->database);
		$this->log = new Log($this->db, $this->ENV, $this->errorHandler);

		// check if we have a database ready:
		try {
			$this->db->connect();
		} catch(\Exception $e) {
			$this->db = NULL;
			$this->errorHandler->add(Array("DB Error", "Could not connect to database", 500, true, ErrorHandler::CRITICAL_ALL));
			$this->errorHandler->sendErrors();
			$this->errorHandler->sendApiErrors();
			exit;
		}


		$this->NOAUTH = isset($this->ENV->Api->noAuth) ? $this->ENV->Api->noAuth : false;
		$this->account = new Models\Account($this->db, $this->ENV, $this->errorHandler, null);

		// put together what was passed as parameters to this api:
		$this->method = $_SERVER['REQUEST_METHOD'];
		$this->requestArray = ApiHelper::getRequest();
		if(count($this->requestArray)===0) {

		}
		$this->data = ApiHelper::getData();


		// AUTHENTICATION
		if($this->_needsAuthentication()) {
			// check if and where we got an authToken
			$headers = getallheaders();
			if(isset($headers['Authorization'])) {
				$auth = explode(" ", $headers['Authorization']);
				$authToken = $auth[1];
				$authType = $auth[0];
			} elseif (isset($this->data->authToken)) {
				$authToken = $this->data->authToken;
			} else {	// no authtoken found -> send error & exit script!
				$this->errorHandler->throwOne(ErrorHandler::AUTH_NO_AUTHTOKEN);
				exit;
			}
			$success = $this->account->reAuthenticate($authToken);

			if(!$this->account->isAuthenticated) {	
				// authorization failed
				$this->errorHandler->throwOne(ErrorHandler::AUTH_FAILED);
				exit;
			} else { 
				// authorization succeeded
				$this->account->updateLastOnline();
			}
		} 
		if($this->ENV->Api->noAuth) {
			$this->account->mockAccount();
		}
		# End Authentication

		$this->models = $this->_getAllModels();
		$this->request = $this->_getFullRequest();

		if($this->request===null || $this->request->type===self::REQUEST_TYPE_INFO) {
				echo ApiInfo::getApiInfo();
				exit;
		}


		switch ($this->method) {
			case 'OPTIONS':
				exit;
			case 'GET':
				require_once('ApiGet.class.php');
				$ApiGet = new ApiGet($this->request, $this->data, $this->ENV, $this->db, $this->errorHandler);
				if($this->request->type===self::REQUEST_TYPE_SPECIAL) {
					$response = $ApiGet->getSpecial();
					if($response) {
						ApiHelper::sendResponse(400,"{ \"success\": ".json_encode($reponse)."}");
					}
				} else {
					$items = $ApiGet->getItems();
					if(isset($items)) {
						ApiHelper::postItems($this->request->model, $items, $this->request->model->modelNamePlural);
					} else {
						$this->errorHandler->throwOne(ErrorHandler::DB_NOT_FOUND);
						exit;
					}
				}
				break;
			case 'POST':
				require_once('ApiPost.class.php');
				$ApiPost = new ApiPost($this->request, $this->data, $this->ENV, $this->db, $this->errorHandler, $this->account, $this->log);
				$items = $ApiPost->postItem();
				ApiHelper::postItems($this->request->model, $items, $this->request->model->modelNamePlural);
				break;
			case 'PUT':
				break;
			case 'DELETE':
				require_once('ApiDelete.class.php');
				$ApiDelete = new ApiDelete($this->request, $this->data, $this->ENV, $this->db, $this->errorHandler, $this->account, $this->log);
				$items = $ApiDelete->deleteItem();
				if($items) {
					ApiHelper::postItems($this->request->model, $items, $this->request->model->modelNamePlural);
				}
				break;
		}
	} // end __construct()


	/**
	*	_getModel
	*	tries to get one model based on it's (plural or singular) name 
	*	
	*	@param [object] environment configuration
	*	@return [object] which has a type, a model, an id
	**/
	private function _getFullRequest() {
		if(count($this->requestArray)===0) {
			// nothing after .../api
			return null;
		}

		$request = new \stdClass();
		$request->type = $this->_determineRequestType();

		if($request->type === self::REQUEST_TYPE_SPECIAL) {
			$request->special = $this->requestArray[0];
			$request->requestArray = $this->requestArray;
		}


		if($request->type === self::REQUEST_TYPE_REFERENCE) {
			// the model to get these items from is always the "bigger" one, the right one
			// user2prduction can be got in Model-Class Production
			// by the method getMany2Many(id, by(id), child-model)
			$request->model = $this->_getModel($references[1]); // always plural
			$request->modelLeft = $this->_getModel($references[0]);	// always singular
			$request->singularRequest = substr($request[0], 0, strlen($request[0])-1);
		}
		if($request->type === self::REQUEST_TYPE_NORMAL || $request->type === self::REQUEST_TYPE_QUERY) {
			$modelName = $this->requestArray[0];
			$model = $this->_getModel($modelName);
			$request->model = $model;
			if (isset($this->requestArray[1]) && is_numeric($this->requestArray[1])) {
				$request->id = $this->requestArray[1];
			} elseif (isset($this->requestArray[1]) && is_string($this->requestArray[1])) {
				$request->special = $this->requestArray[1];
			} else {
				// 3. if we have NO id on position 2 and it's a PUT or DELETE we have an ERROR
				if($this->method==='PUT' || $this->method==='DELETE') {
					$this->errorHandler->throwOne(ErrorHandler::API_INVALID_POSTPUT_REQUEST);
				}
			} 
		}
		return $request;
	}

	/**
	*	_determineRequestType
	*	tries to determine the request type based on:
	* 	- whats in request
	*	- what's in data
	*	
	*	@param 
	*	@return [int] Constant REQUEST_TYPE_*
	**/
	private function _determineRequestType() {
		if($this->requestArray[0]==='' || strtolower($this->requestArray[0])==='apiInfo') {
			return self::REQUEST_TYPE_INFO;
		}
		// check for comment2post type 'references'
		$references = explode("2", $this->requestArray[0]);
		if(count($references)===2) {
			return self::REQUEST_TYPE_REFERENCE;
		}
		if ((isset($this->requestArray[1]) && $this->requestArray[1]==='multiple') || isset($this->data->ids)) {
			return self::REQUEST_TYPE_COALESCE;
		}
		if(in_array($this->requestArray[0], $this->specialVerbs)) {
			return self::REQUEST_TYPE_SPECIAL;
		}
		if(isset($this->data->filter) || isset($this->data->gt) || isset($this->data->gte) || isset($this->data->lt) || isset($this->data->lte)) {
			return self::REQUEST_TYPE_QUERY;
		}
		// default
		return self::REQUEST_TYPE_NORMAL;
	}


	/**
	*	_getModel
	*	tries to get one model based on it's (plural or singular) name 
	*	
	*	@param [object] environment configuration
	*	@return [array of models]
	**/
	private function _getModel($modelName) {
		if($this->models) {
			// if we already scanned the directory (which we should have done already), we can simply check if it's in there and return true
			if(isset($this->models[$modelName])) {
				// found as plural version
				return $this->models[$modelName];
			}
			// now check for singular version.
			// I think we need to walk through all available models and get the singleName out of that.
			foreach ($this->models as $model) {
				if($model->modelName==$modelName) {
					return $model;
				}
			}
		}

		$modelFile = dirname(__FILE__).DIRECTORY_SEPARATOR.$this->ENV->dirs->models . ucfirst($modelName) . ".php";
		if (!file_exists($modelFile)) {
			$this->errorHandler->throwOne(Array("Api Error", "Requested recource '{$modelName}' not found/defined.", 400, false, ErrorHandler::CRITICAL_EMAIL));
			exit;
		} else {
			include_once($modelFile);
			$classNameNamespaced = "\\Jeff\\Api\\Models\\" . ucfirst($modelName);
			$model = new $classNameNamespaced($this->db, $this->ENV, $this->errorHandler);
			return $model;
		}
		return null;
	}


	/**
	*	_getAllModels
	*	walkes the App's models folder and instanciates every found model 
	*	and returns them as array of models with the modelName as key
	*	
	*	@param [object] environment configuration
	*	@return [array of models]
	**/
	private function _getAllModels() {
		$models = Array();
		$folder = $this->ENV->dirs->models;
		$files = array_diff(scandir($folder), array('.', '..'));
		foreach ($files as $fileName) {
			include_once($folder.DIRECTORY_SEPARATOR.$fileName);
			$className = basename($fileName, ".php");
			$classNameNamespaced = "\\Jeff\\Api\\Models\\" . ucfirst($className);
			$model = new $classNameNamespaced($this->db, $this->ENV, $this->errorHandler, $this->account);
			$models[$model->modelNamePlural] = $model;
		}
		return $models;
	}


	/**
	*	_needsAuthentication
	*	
	*	@param [array] the uri request, [object] environment configuration
	*	@return [bool]
	**/
	private function _needsAuthentication() {
		// in Config we can defaine a set of routes (=the request string 'posts', 'login', 'task/redirect')
		// which will be accessable without authentication.
		// This is especially needed for singup, login, special tasks.

		if(isset($this->ENV->Api->noAuthRoutes) && is_array($this->ENV->Api->noAuthRoutes) && is_array($this->requestArray)) {
			$requestRoute = implode('/', $this->requestArray);
			foreach ($this->ENV->Api->noAuthRoutes as $key => $route) {
				if($route===$requestRoute) {
					return false;
				}
			}
		}
		if($this->ENV->Api->noAuth) { 
			return false;
		}
		return true;
	}



	/**
	*	_sendPrimaryHeader
	*	
	*	@param [object] environment configuration
	*	@return [void]
	**/
	private function _sendPrimaryHeaders() {
		header("Access-Control-Allow-Origin: ".$this->ENV->urls->allowOrigin);
		header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
		header("Access-Control-Allow-Headers: Origin, Content-Type, Authorization, X-Custom-Auth");	
	}


}
