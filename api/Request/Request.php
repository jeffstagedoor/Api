<?php


namespace Jeff\Api\Request;
use Jeff\Api;
use Jeff\Api\Models;
use Jeff\Api\Log;

require_once("RequestType.php");

/**
 * class Request, that handles with it's subclasses everything about a request and keeps the sent request data
 */
class Request {

    /** @var Array containing the request-parameters */
    public $params = [];

    /** @var Object containing the request-data */
    public $data;

    /** @var RequestType the type of the request. such as 'NORMAL', 'COALESQUE',.. */
    public $type;

    /** @var string if the request if of RequestType::SPECIAL we will store the special verb in here */
    public $specialVerb;

    public $modelLeft;

    /**
     * Constructor
     * 
     * sets RequestArray, data
     */
    public function __construct() {
        // set RequestArray
        $this->params = $this->setParams();
        $this->data = $this->findData();
        $this->type = $this->determineRequestType();
    }

    public function setup() {
        if(count($this->params)===0) {
			// nothing after .../api
			return null;
		}

		if($this->type === RequestType::SPECIAL) {
			$this->special = $this->params[0];
		}


		if($this->type === RequestType::REFERENCE) {
			// the model to get these items from is always the "bigger" one, the right one
			// user2prduction can be got in Model-Class Production
			// by the method getMany2Many(id, by(id), child-model)
			$this->model = Api::_getModel($this->references[1]); // always plural
			$this->modelLeft = Api::_getModel($this->references[0]);	// always plural
			if (isset($this->params[1]) /*&& is_numeric($this->requestArray[1])*/) { // will be a string for Many2Many eg "24_30"
				$this->id = $this->params[1];
			}
			#$request->singularRequest = substr($this->requestArray[0], 0, strlen($this->requestArray[0])-1);
		}
		if($this->type === RequestType::NORMAL 
			|| $this->type === RequestType::QUERY 
			|| $this->type=== RequestType::COALESCE) {
			
			$modelName = $this->params[0];
			$model = Api::_getModel($modelName);
			$this->model = $model;
			if (isset($this->params[1]) && is_numeric($this->params[1])) {
				$this->id = $this->params[1];
			} elseif (isset($this->params[1]) && is_string($this->params[1])) {
				$this->specialVerb = $this->params[1];
			} else {
				// 3. if we have NO id on position 2 and it's a PUT or DELETE we have an ERROR
				if($this->method==='PUT' || $this->method==='DELETE') {
					ErrorHandler::throwOne(ErrorHandler::API_ID_MISSING);
				}
			} 
		}
    }

	/**
	*
	* @return array containing the request-parameters 
	*               When calling the api with http://example.com/api/modelName/5
	*               this will contain everything after 'api' split by '/'
	*               -> ['modelName', '5']
	*/
    public static function setParams() {
		if(isset($_GET['request'])) {
			$request = explode("/",$_GET['request']);
		} else {
			$request = null;
		}
		return $request;
    }
    
    /**
	*	tries to fetch data where ever it might be posted/put/...
	*	@return object the posted data as stdClass
	*/
	public static function findData() {
		// check where the data came to (and if at all):
		$fgc = file_get_contents("php://input");

		// test if sent body is a json (that's the usual case for POST, DELETE requests)
		$json = json_decode($fgc, true);
		if($json) {
			$inputData = (Object) $json;
			if(isset($inputData) && count(get_object_vars($inputData))>0) {
				$data = $inputData;
				return $data;
			}
		} else {
			// check for PUT (or 'application/x-www-form-urlencoded')
			parse_str($fgc, $putData);
			if($putData && count($putData)>0) {
				$data = (Object) $putData;
				return $data;
			}
		}
		$postObject = (Object) $_POST;
		if(isset($postObject) && count(get_object_vars($postObject))>0) {
			$data = $postObject;
		}

		// check for get-parameters
		if(!isset($data)) {	
			$data = (Object) $_GET;
			unset($data->request);
		}
		// if nothing found anywhere make an empty object
		if(!isset($data)) {
			$data = new \stdClass();
		}
		return $data;
    }
    
    /**
	*	tries to determine the request type based on:
	* 	- what's in request
	*	- what's in data
	*	
	*	@return RequestType
	*/
	private function determineRequestType() {
		if($this->params[0]==='' || strtolower($this->params[0])==='apiInfo') {
			return RequestType::INFO;
		}
		// check for comment2post type 'references'
		$references = explode("2", $this->params[0]);
		if(count($references)===2) {
			$this->references = $references;
			return RequestType::REFERENCE;
		}
		if ((isset($this->params[1]) && $this->params[1]==='multiple') || isset($this->data->ids)) {
			return RequestType::COALESCE;
		}
		if(in_array($this->params[0], Api::specialVerbs)) {
			return RequestType::SPECIAL;
		}
		if(isset($this->data->filter) || isset($this->data->gt) || isset($this->data->gte) || isset($this->data->lt) || isset($this->data->lte)) {
			return RequestType::QUERY;
		}
		// default
		return RequestType::NORMAL;
	}

}