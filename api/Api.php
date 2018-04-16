<?php
/**
*
* A REST API
*
* This is the main entrance file/class for Jeff's Rest-API
*
* @author Jeff Frohner <office@jefffrohner.com>
* @copyright Jeff Frohner 2018
* @version 1.9.9
* @package Jeff\Api
*
*/

namespace Jeff\Api;
use Jeff\Api\Environment;
use Jeff\Api\Request;
use Jeff\Api\Request\RequestType;
use Jeff\Api\Request\RequestMethod;

require_once("Request/Request.php");
require_once("Environment.php");
require_once("ErrorHandler.php");
require_once("Log/Log.php");
require_once("Database/Helper.php");
require_once("ApiHelper.php");
require_once("Models/Model.php");
require_once("Models/Account.php");
require_once("MailerPrototype.php");
require_once("Authorizor/Authorizor.php");
require_once("Utils/DataMasker.php");
include_once("Utils/debughelpers.php");
require_once(Environment::$dirs->vendor.'joshcam/mysqli-database-class/MysqliDb.php');

/**
 * a class that defines and shows version, author, etc. of this package
 *
 * @author Jeff Frohner <office@jefffrohner.com>
 * @copyright Jeff Frohner 2017
 * @version 1.9.9
 * @package Jeff\Api
 */
Class ApiInfo {
	/** @var string vesion of this Api class */
	public static $version = "1.3.1";
	/** @var string Author */
	public static $author = "Jeff Frohner";
	/** @var string when I coded that */
	public static $year = "2017";
	/** @var string the licence */
	public static $licence = "MIT";
	/** @var string what kid of API this is. Now REST */
	public static $type = "REST";
	/** @var string just a string to say where this API can be used*/
	public static $restriction = "authorized apps and logged in users only";

	/**
	*	returns a collection of ApiInfos as json (default)
	*	
	*	@param string $format ('array', 'json'=default) - NOT IMPLEMENTED
	*	@return json
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

/**
 * This is the main entrance class for Jeff's Rest-API.
 *
 * I'd suggest some major documenting here.
 *
 * @author Jeff Frohner <office@jefffrohner.com>
 * @copyright Jeff Frohner 2017
 * @version 1.9.9
 * @package Jeff\Api
 */
Class Api {
	
	/** @var instance of API */
	static private $instance = null;

	/** 
	 * for development only - disables authorization. Set in Environment.
	 * @var boolean 
	 */
	private $NOAUTH=false;

	/** 
	 * a collection of special verbs that will be treated as a 'special request'.
	 * usually verbs like _'login', 'signup', 'task', 'search'_.      
	 *
	 * These are the ___verbs pre-defined__, though the param can be overriden:      
	 *	
	 * ```
	 * Array('dbupdate','meta', 'login', 'signup', 'signin', 'task', 
	 *       'sort', 'search', 'count', 'apiInfo', 'getFile', 'getImage', 
	 *       'getFolder', 'fileUpload', 'changePassword', 'changeName');
	 * ```
	 * 
	 * @var array 	
	 */
	private $specialVerbs = Array('dbupdate','meta', 'login', 'signup', 'signin', 'task', 'sort', 'import', 'search', 'count', 'apiInfo', 
									'getFile', 'getImage','getFolder',
									'fileUpload', 'changePassword', 'changeName'
									);

	/** @var array all the models found in this installation */
	private $models;
	
	/** @var object an Object that describes the current request */
	private $request;
	
	/** @var object all the data that has been sent with the request */
	private $data;

	// /** @var Jeff\Api\Log\Log The Logging class */
	// private $log;

    /**
     * Call this method to get singleton
     *
     * @return Api
     */
    public static function getInstance()
    {
		if (null === self::$instance) {
			self::$instance = new Api();
		}
		return self::$instance;
	}
	

	/**
    * disallow __construct
    */		
	private function __construct() 	{}

		
	/**
	* disallow __clone
	*/		
	protected function __clone() {}

	/**
	 * The startup
	 *
	 * Will _instanciate_ an
	 *
	 * - MysqliDb (and assign to this->db)
	 * - Account
	 *	
	 * will connect to database (throws Errors if not successfull).
	 *
	 * will analyse the made $request, get needed $models, authorize the current user     
	 * and will delegate to ApiGet, ApiPost, ApiPut, ApiDelete depending on the request made.
	 *	
	 */
	public function start() {
		
		// instatiate all nesseccary classes
		$this->db = new \MysqliDb(Environment::$database);
		Log\Log::init($this->db);
		

		self::_sendPrimaryHeaders();
		// new: $this->response->sendPrimaryHeaders();

		// check if we have a database ready:
		try {
			$this->db->connect();
		} catch(\Exception $e) {
			$this->db = NULL;
			ErrorHandler::add(Array("DB Error", "Could not connect to database", 500, true, ErrorHandler::CRITICAL_ALL));
			ErrorHandler::sendErrors();
			ErrorHandler::sendApiErrors();
			exit;
		}


		$this->NOAUTH = isset(Environment::$api->noAuth) ? Environment::$api->noAuth : false;
		$this->account = new Models\Account($this->db, null);

		// put together what was passed as parameters to this api:
		#$this->method = $_SERVER['REQUEST_METHOD'];
		#$this->requestArray = ApiHelper::getRequest();
		#$this->data = ApiHelper::getData();
		$this->models = $this->_getAllModels();
		$this->request = new Request\Request();
		$this->request->init();
		#$this->request = $this->_getFullRequest();
		// var_dump($this->request);
		if(count($this->request->params)===0 || $this->request->type===Request\RequestType::INFO) {
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
			} elseif (isset($this->request->data->authToken)) {
				$authToken = $this->request->data->authToken;
			} else {	
				// no authToken found.

				// now we have one special case when an invitationCode is sent - when on publicPage to accept invitation.
				if(isset($this->request->data->filter['invitationToken']) && $this->requestArray[0]==='accounts') {
					$invitationToken = $this->request->data->filter['invitationToken'];
				} else {
					// no authtoken, no invitationToken found -> send error & exit script!
					ErrorHandler::throwOne(ErrorHandler::AUTH_NO_AUTHTOKEN);
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
				ErrorHandler::throwOne(ErrorHandler::AUTH_FAILED);
				exit;
			} else { 
				// authorization succeeded
				$this->account->updateLastOnline();
			}
		} 
		if(Environment::$api->noAuth) {
			$this->account->mockAccount();
		}
		# End Authentication


		// some specials before regular API call
		// dbupdate
		if($this->request->type===RequestType::SPECIAL && $this->request->special==='dbupdate') {
			if(isset($this->request->params[1]) && $this->request->params[1]==='execute') {
				$execute = true;
			} else {
				$execute = false;
			}
			$dbHelper = new Database\Helper($this->db);
			$dbHelper->update($execute, $this->request->params);
			exit;
			echo "end of request dbupdate";
		}

		switch ($this->request->method) {
			case RequestMethod::OPTIONS:
				exit;
			case RequestMethod::GET:
				require_once('ApiGet.php');
				$ApiGet = new ApiGet($this->request, $this->db, $this->account);
				if($this->request->type===RequestType::SPECIAL) {
					$response = $ApiGet->getSpecial();
					if($response) {
						ApiHelper::sendResponse(200,json_encode($response));
					} else {
						ErrorHandler::throwOne(42);
						exit;
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

						ErrorHandler::throwOne(ErrorHandler::DB_NOT_FOUND);
						exit;
					}
				}
				break;
			case RequestMethod::POST:
				require_once('ApiPost.php');
				$ApiPost = new ApiPost($this->request, $this->db, $this->account);
				if($this->request->type===RequestType::SPECIAL) {
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
			case RequestMethod::PUT:
				require_once('ApiPut.php');
				$ApiPut = new ApiPut($this->request, $this->db, $this->account);
				if($this->request->type===RequestType::SPECIAL) {
					$response = $ApiPut->putSpecial(/*$this->models*/);
					if($response) {
						ApiHelper::sendResponse(200,json_encode($response));
					}
				} else {
					$response = $ApiPut->putItem();
					ApiHelper::sendResponse(200,json_encode($response));
				}
				break;
			case RequestMethod::DELETE:
				require_once('ApiDelete.php');
				$ApiDelete = new ApiDelete($this->request, $this->request->data, $this->db, $this->account);
				$response = $ApiDelete->deleteItem();
				ApiHelper::sendResponse(200,json_encode($response));
				break;
			case RequestMethod::PATCH:
				echo "Method PATCH not implemented";
				break;
		}
	} // end start

	/**
	 * getter for private property $specialVerbs
	 */
	public function getSpecialVerbs() {
		return $this->specialVerbs;
	}

	/**
	* tries to get one model based on it's (plural or singular) name 
	*	
	* @param string $modelName  name of the desired model
	* @return array array of models
	*/
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
		$modelFile = Environment::$dirs->models . ucfirst($modelName) . ".php";
		
		if (!file_exists($modelFile)) {
			ErrorHandler::throwOne(Array("Api Error", "Requested recource '{$modelName}' not found/defined.", 400, ErrorHandler::CRITICAL_EMAIL, false));
			exit;
		} else {
			include_once($modelFile);
			$classNameNamespaced = "\\Jeff\\Api\\Models\\" . ucfirst($modelName);
			$model = new $classNameNamespaced($this->db, $this->account);
			return $model;
		}
		return null;
	}


	/**
	*	walkes the App's models folder and instanciates every found model 
	*	and returns them as array of models with the modelName as key
	*	
	*	@return array of models
	*/
	private function _getAllModels(): array {
		$models = Array();
		$folder = Environment::$dirs->models;
		$files = array_diff(scandir($folder), array('.', '..'));
		foreach ($files as $fileName) {
			include_once($folder.DIRECTORY_SEPARATOR.$fileName);
			$className = basename($fileName, ".php");
			$classNameNamespaced = "\\Jeff\\Api\\Models\\" . ucfirst($className);
			$model = new $classNameNamespaced($this->db, $this->account);
			$models[$model->modelNamePlural] = $model;
		}
		return $models;
	}


	/**
	*	Checks if a route needs to be authenticated
	* 
	*	in Config we can define a set of routes (=the request string 'posts', 'login', 'task/redirect')
	*	which will be accessable without authentication.
	*	This is especially needed for singup, login, special tasks.
	*	
	*	@return boolean
	*/
	private function _needsAuthentication() {
		if(isset(Environment::$api->noAuthRoutes) && is_array(Environment::$api->noAuthRoutes) && is_array($this->request->params)) {
			$requestRoute = implode('/', $this->request->params);
			foreach (Environment::$api->noAuthRoutes as $key => $route) {
				if($route===$requestRoute) {
					return false;
				}
			}
		}
		if(Environment::$api->noAuth) { 
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
	*	@return void
	*/
	private function _sendPrimaryHeaders() {
		header("Access-Control-Allow-Origin: ".Environment::$api->allowOrigin);
		header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
		header("Access-Control-Allow-Headers: Origin, Content-Type, Authorization, X-Custom-Auth");	
	}

}
