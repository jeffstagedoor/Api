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

// Api::sendPrimaryHeaders($ENV);
// header("Access-Control-Allow-Origin: ".$ENV->urls->allowOrigin);
// header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
// header("Access-Control-Allow-Headers: AUTHORIZATION");

require_once($ENV->dirs->vendor.'joshcam/mysqli-database-class/MysqliDb.php');

require_once("ErrorHandler.php");
require_once("Log.php");
require_once("DataMasker.php");
require_once("DBHelper.php");
require_once("ApiHelper.php");
require_once("Model.php");
require_once("Account.php");
require_once("MailerPrototype.php");
require_once("Authorizor/Authorizor.php");

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
	public static function getApiInfo(string $format='json') {
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
	private $specialVerbs = Array('dbupdate','meta', 'login', 'signup', 'signin', 'task', 'sort', 'search', 'count', 'apiInfo', 
									'getFile', 'getImage','getFolder',
									'fileUpload', 'changePassword'
									);
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

	


	public function __construct(Environment $ENV=null) {
		
		$this->ENV = $ENV;
		// instatiate all nesseccary classes
		$this->errorHandler = new ErrorHandler();
		$this->db = new \MysqliDb($this->ENV->database);
		$this->log = new Log($this->db, $this->ENV, $this->errorHandler);

		self::_sendPrimaryHeaders($ENV);

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


		$this->NOAUTH = isset($this->ENV->api->noAuth) ? $this->ENV->api->noAuth : false;
		$this->account = new Models\Account($this->db, $this->ENV, $this->errorHandler, null);

		// put together what was passed as parameters to this api:
		$this->method = $_SERVER['REQUEST_METHOD'];
		$this->requestArray = ApiHelper::getRequest();
		$this->data = ApiHelper::getData();
		$this->models = $this->_getAllModels();
		$this->request = $this->_getFullRequest();
		#var_dump($this->models);
		if(count($this->requestArray)===0 || $this->request===null || $this->request->type===self::REQUEST_TYPE_INFO) {
			echo ApiInfo::getApiInfo();
			exit;
		}


		// AUTHENTICATION
		if($this->_needsAuthentication()) {
			// check if and where we got an authToken
			$headers = getallheaders();
			if(isset($headers['Authorization']) || isset($headers['authorization'])) { // sometimes it's send in lowercase... f.e. in AdvancedRestClient
				$authorization = isset($headers['Authorization']) ? $headers['Authorization'] : $headers['authorization'];
				$auth = explode(" ", $authorization);
				$authToken = $auth[1];
				$authType = $auth[0];
			} elseif (isset($this->data->authToken)) {
				$authToken = $this->data->authToken;
			} else {	
				// no authToken found.

				// now we have one special case when an invitationCode is sent - when on publicPage to accept invitation.
				if(isset($this->data->filter['invitationToken']) && $this->requestArray[0]==='accounts') {
					$invitationToken = $this->data->filter['invitationToken'];
				} else {
					// no authtoken, no invitationToken found -> send error & exit script!
					$this->errorHandler->throwOne(ErrorHandler::AUTH_NO_AUTHTOKEN);
					exit;
				}
			}
			if(isset($invitationToken)) {
				$success = $this->account->reAuthenticateByInvitationToken($invitationToken);
			} else {
				$success = $this->account->reAuthenticate($authToken);
			}

			if(!$this->account->isAuthenticated) {	
				// authorization failed
				$this->errorHandler->throwOne(ErrorHandler::AUTH_FAILED);
				exit;
			} else { 
				// authorization succeeded
				$this->account->updateLastOnline();
			}
		} 
		if($this->ENV->api->noAuth) {
			$this->account->mockAccount();
		}
		# End Authentication


		// some specials before regular API call
		// dbupdate
		if($this->request->type===self::REQUEST_TYPE_SPECIAL && $this->request->special==='dbupdate') {
			if(isset($this->request->requestArray[1]) && $this->request->requestArray[1]==='execute') {
				$execute = true;
			} else {
				$execute = false;
			}
			$dbHelper = new DBHelper($this->db, $this->errorHandler);
			$dbHelper->update($this->ENV, $execute, $this->request->requestArray);
		}

		switch ($this->method) {
			case 'OPTIONS':
				exit;
			case 'GET':
				require_once('ApiGet.php');
				$ApiGet = new ApiGet($this->request, $this->data, $this->ENV, $this->db, $this->errorHandler, $this->account);
				if($this->request->type===self::REQUEST_TYPE_SPECIAL) {
					$response = $ApiGet->getSpecial();
					if($response) {
						ApiHelper::sendResponse(200,json_encode($response));
					}
				} else {
					$items = $ApiGet->getItems();
					if(isset($items)) {
						// add sideloads if present
						if($this->request->model->sideload) {
							foreach ($this->request->model->sideload as $key => $sideload) {
								$items->{$key} = $sideload;
							}
						}
						ApiHelper::sendResponse(200,json_encode($items));
						// ApiHelper::postItems($this->request->model, $items, $this->request->model->modelNamePlural);
					} else {
						$this->errorHandler->throwOne(ErrorHandler::DB_NOT_FOUND);
						exit;
					}
				}
				break;
			case 'POST':
				require_once('ApiPost.php');
				$ApiPost = new ApiPost($this->request, $this->data, $this->ENV, $this->db, $this->errorHandler, $this->account, $this->log);
				if($this->request->type===self::REQUEST_TYPE_SPECIAL) {
					$response = $ApiPost->postSpecial();
					if($response) {
						ApiHelper::sendResponse(200,json_encode($response));
					}
				} else {
					$response = $ApiPost->postItem();
					#echo "response in Api.php Line ".__LINE__."\n";
					#var_dump($response);
					ApiHelper::sendResponse(200, json_encode($response));
				}
				break;
			case 'PUT':
				require_once('ApiPut.php');
				$ApiPut = new ApiPut($this->request, $this->data, $this->ENV, $this->db, $this->errorHandler, $this->account, $this->log);
				if($this->request->type===self::REQUEST_TYPE_SPECIAL) {
					$response = $ApiPut->putSpecial($this->models);
					if($response) {
						ApiHelper::sendResponse(200,json_encode($response));
					}
				} else {
					$response = $ApiPut->putItem();
					ApiHelper::sendResponse(200,json_encode($response));
				}
				break;
			case 'DELETE':
				require_once('ApiDelete.php');
				$ApiDelete = new ApiDelete($this->request, $this->data, $this->ENV, $this->db, $this->errorHandler, $this->account, $this->log);
				$response = $ApiDelete->deleteItem();
				ApiHelper::sendResponse(200,json_encode($response));
				break;
		}
	} // end __construct()


	/**
	*	_getFullrequest
	*	getting a full Request Object with type, model, an id
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
		$request->requestArray = $this->requestArray;

		if($request->type === self::REQUEST_TYPE_SPECIAL) {
			$request->special = $this->requestArray[0];
			$request->requestArray = $this->requestArray;
		}


		if($request->type === self::REQUEST_TYPE_REFERENCE) {
			// the model to get these items from is always the "bigger" one, the right one
			// user2prduction can be got in Model-Class Production
			// by the method getMany2Many(id, by(id), child-model)
			$request->model = $this->_getModel($this->references[1]); // always plural
			$request->modelLeft = $this->_getModel($this->references[0]);	// always plural
			if (isset($this->requestArray[1]) /*&& is_numeric($this->requestArray[1])*/) { // will be a string for Many2Many eg "24_30"
				$request->id = $this->requestArray[1];
			}
			#$request->singularRequest = substr($this->requestArray[0], 0, strlen($this->requestArray[0])-1);
		}
		if($request->type === self::REQUEST_TYPE_NORMAL 
			|| $request->type === self::REQUEST_TYPE_QUERY 
			|| $request->type=== self::REQUEST_TYPE_COALESCE) {
			
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
			$this->references = $references;
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
	*	@param [string] name of the desired model
	*	@return [array of models]
	**/
	public function _getModel(string $modelName) {
		if($this->models) {
			// if we already scanned the directory (which we should have done already), we can simply check if it's in there and return true
			if(isset($this->models[$modelName])) {
				// found as plural version
				return $this->models[$modelName];
			}
			// now check for singular version.
			// I think we need to walk through all available models and get the singleName out of that.
			foreach ($this->models as $model) {
				if($model->modelName===$modelName) {
					return $model;
				}
			}
		}
		$modelFile = $this->ENV->dirs->models . ucfirst($modelName) . ".php";
		
		if (!file_exists($modelFile)) {
			$this->errorHandler->throwOne(Array("Api Error", "Requested recource '{$modelName}' not found/defined.", 400, false, ErrorHandler::CRITICAL_EMAIL));
			exit;
		} else {
			include_once($modelFile);
			$classNameNamespaced = "\\Jeff\\Api\\Models\\" . ucfirst($modelName);
			$model = new $classNameNamespaced($this->db, $this->ENV, $this->errorHandler, $this->account);
			return $model;
		}
		return null;
	}


	/**
	*	_getAllModels
	*	walkes the App's models folder and instanciates every found model 
	*	and returns them as array of models with the modelName as key
	*	
	*	@param
	*	@return array of models
	**/
	private function _getAllModels(): array {
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
	*	Checks if a route needs to be authenticated
	* 
	*	in Config we can defaine a set of routes (=the request string 'posts', 'login', 'task/redirect')
	*	which will be accessable without authentication.
	*	This is especially needed for singup, login, special tasks.
	*	
	*	@param [array] the uri request, [object] environment configuration
	*	@return boolean
	**/
	private function _needsAuthentication() {
		#echo __FILE__." ". __FUNCTION__ ."() - Line: ". __LINE__."\n";
		if(isset($this->ENV->api->noAuthRoutes) && is_array($this->ENV->api->noAuthRoutes) && is_array($this->requestArray)) {
			$requestRoute = implode('/', $this->requestArray);
			foreach ($this->ENV->api->noAuthRoutes as $key => $route) {
				if($route===$requestRoute) {
					return false;
				}
			}
		}
		if($this->ENV->api->noAuth) { 
			return false;
		}
		if($this->method==='OPTIONS') {
			return false;
		}
		return true;
	}



	/**
	*	sends a default primary header
	*
	*	This is needed at least for an _OPTIONS_ request
	* 	But this headers will be sent with _every_ response.
	*	
	*	@param stdClass environment configuration
	*	@return void
	**/
	private function _sendPrimaryHeaders($ENV) {
		header("Access-Control-Allow-Origin: ".$this->ENV->urls->allowOrigin);
		header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
		header("Access-Control-Allow-Headers: Origin, Content-Type, Authorization, X-Custom-Auth");	
	}


}
