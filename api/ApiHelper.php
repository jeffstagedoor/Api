<?php
/**
*	Class ApiHelper
*	
*	Helper functions for API
*
*
*	all functions MUST be called statically
*	@author Jeff Frohner
*	@copyright Copyright (c) 2016
*	@license   private
*	@version   1.0
*
**/
namespace Jeff\Api;

Class ApiHelper {
		

	/**
	*	getRequest
	*
	**/
	public static function getRequest() {
		if(isset($_GET['request'])) {
			$request = explode("/",$_GET['request']);
		} else {
			$request = null;
		}
		return $request;
	}


	/**
	*	getData
	*	tries to fetch data where ever it might be posted/put/...
	*	@param
	*	@return [stdClass] the posted data as stdClass
	**/
	public static function getData() {
		// check where the data came to (and if at all):
		$fgc = file_get_contents("php://input");
		#var_dump($fgc);
		$inputData = (Object) json_decode($fgc, true);

		#var_dump($inputData);
		if(isset($inputData) && count(get_object_vars($inputData))>0) {
			$data = $inputData;
		}
		#var_dump($data);
		$postObject = (Object) $_POST;
		if(isset($postObject) && count(get_object_vars($postObject))>0) {
			$data = $postObject;
		}
		#var_dump($data);

		// check for get-parameters
		if(!isset($data)) {	
			$data = (Object) $_GET;
			unset($data->request);
		}
		// check for PUT
		#parse_str($fgc, $putData);
		#var_dump($putData);
		// if nothing found anywhere make an empty object
		if(!isset($data)) {
			$data = new \stdClass();
		}
		return $data;
	}

	/**
	*	getRequiredFields
	*	tries to find the requested model
	*	@param [string] dbDefinitions
	*	@return [array] the required field
	**/
	public static function getRequiredFields($dbDefinition) {
		$required = array();
		foreach ($dbDefinition as $index => $field) {
			if(
				(!isset($field[3]) || (isset($field[3]) && $field[3]===false))	// may NOT be NULL
				&&
				(!isset($field[5]) || (isset($field[5]) && $field[5]!='AUTO_INCREMENT')) // no Auto_Increment
				&&
				(!isset($field[4]) || (isset($field[4]) && ($field[4]===false || $field[4]==null))) // has no Default
				) 
			{
				$required[]=$field;
			}
		}
		return $required;
	}

	/**
	*	allRequiredFieldsSet
	*	tries to find the requested model
	*	@param [array] requiredFields, [array] the received dataset
	*	@return [boolean]
	**/
	public static function checkRequiredFieldsSet($required, $data) {
		$missing = Array();
		foreach ($required as $key => $field) {
			if(!isset($data[$field[0]])) {
				$missing[] = $field[0];
			}
		}
		return $missing;
	}


	/**
	*	sendResponse
	*	echoes the given [json] reponse
	*	@param [int] http_response_code, [string/json] response, ([string/json] content_type)
	*	@return nothing
	**/
	public static function sendResponse($http_response_code, $response, $content_type="json") {
		http_response_code($http_response_code);
		switch ($content_type) {
			case "json":
				header("Content-Type: application/json");
				break;
			default:
				header("Content-Type: application/json");
		}
		echo $response;
	}


	/**
	*	sendMeta
	*	wrapper for sendResponse, which just wraps the payload in a meta-object
	*	@param [obj]
	*	@return nothing
	**/
	public static function sendMeta($response) {
		self::sendResponse(200, "{\"meta\": ".json_encode($response)." }");
	}


	public static function postItems($model, $items, $modelName=null, $format='json') {
		if(!isset($items)) {
			throw new \Exception("Error Processing Request: no item found", 1);
		}
		if(is_null($modelName)) $modelName = $model->modelNamePlural; 
		if($format==='json') {
			$datastring = "{ ";
			$datastring .= "\"".$modelName."\": ". json_encode($items);
			$datastring .= " } ";
			self::sendResponse(200, $datastring);
		} else {
			throw new \Exception("Error Processing Request: invalid format given: ".$format, 1);
		}
	}

	public static function postItem($model, $item, $modelName=null, $sideload=null, $format='json') {
		if(!isset($item)) {
			throw new \Exception("Error Processing Request: no item found: ".$item, 1);
		}
		if(is_null($modelName)) $modelName = $model->modelName;
		if($format==='json') {
			$datastring = "{ ";
			$datastring .= "\"".$modelName."\": ". json_encode($item);
			if($sideload) {
				$datastring .= self::addSideload($sideload);
			}
			$datastring .= " } ";
			self::sendResponse(200, $datastring);
		} else {
			throw new \Exception("Error Processing Request: invalid format given: ".$format, 1);
			
		}
	}

	private static function addSideload($sideload) {
		echo "DEPRECATED: ApiHelper->addSideLoad\n";
		$x="";
		foreach ($sideload as $key => $value) {
			$x .= ", \"".$key."\": ".json_encode($value);
		}
		return $x;
	}



	public static function writeLog($itemName, $data, $action, $ENV, $Account, $log) {
		echo "DEPRECATED: ApiHelper->writeLog\n";
		$logConfig = __DIR__.DIRECTORY_SEPARATOR.$ENV->dirs->appRoot."LogConfig.php";
		if(file_exists($logConfig)) {
			include_once($logConfig);
			$log->write($Account->id, $action, $itemName, $data);
		} else {
			$err = new Error(ErrorHandler::LOG_NO_CONFIG);
		}
	}

	public static function showApiInfo() {
		global $apiInfo;
		echo json_encode($apiInfo);
	}

}
