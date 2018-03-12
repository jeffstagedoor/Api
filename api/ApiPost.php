<?php
/**
*	Class ApiPost
*	
*	@author Jeff Frohner
*	@copyright Copyright (c) 2017
*	@license   private
*	@version   1.0
*
**/
namespace Jeff\Api;

Class ApiPost
{
	private $db;
	private $account;
	private $request;
	private $ENV;
	private $items;
	private $response;

	function __construct($request, $data, $ENV, $db, $errorHandler, $account, $log) {
		$this->request = $request;
		$this->data = $data;
		$this->ENV = $ENV;
		$this->db = $db;
		$this->errorHandler = $errorHandler;
		$this->account = $account;
		$this->log = $log;
	}

	public function postItem() {
		$this->response = new \stdClass();
		switch ($this->request->type) {
			case Api::REQUEST_TYPE_REFERENCE: 
				$model = $this->request->model;
				$modelLeft = $this->request->modelLeft;
				#var_dump($this->data);
				#var_dump($this->data->{$model->modelName});
				$itemName = $modelLeft->modelNamePlural.'2'.$model->modelName;
				if(!isset($this->data->{$itemName})) {
					$this->errorHandler->throwOne(41);
					exit;
				}
				$dataSet = $this->data->{$itemName};
				$dataSet['modBy'] = $this->account->id;
				unset($dataSet['modDate']);
				$id = $model->addMany2Many($modelLeft, $dataSet);
				
				if($this->errorHandler->hasErrors()) {
					$this->errorHandler->sendAllErrorsAndExit();
				}

				$this->response->{$itemName} = $this->request->model->getMany2Many($id, $modelLeft->modelNamePlural);
				$this->data->{$itemName}['id'] = $id;
				$this->log->write($this->account->id, $itemName."Add", $itemName, $this->data);
				return $this->response;
				break;
			case Api::REQUEST_TYPE_COALESCE:
				$items = $this->request->model->addMultiple($this->data->{$this->request->model->modelName}, $this->data->multipleParams);
				$this->response->{$this->request->model->modelNamePlural} = $items;
				$logData = new \stdClass();
				$logData->for = new LogDefaultFor(NULL,\Constants::USER_ADMIN,NULL,NULL,NULL,NULL,NULL,NULL);
				$logData->meta = new LogDefaultMeta(NULL,NULL, count($items),NULL, json_encode($this->data->multipleParams));
				$this->log->write($this->account->id, "createMultiple", $this->request->model->modelName, $logData);
				return $this->response;
				break;
			case Api::REQUEST_TYPE_QUERY:
				$this->errorHandler->add(array("API Error", "I received a Post request with a filter. Not implemented, doesn't make sense.",500,true, Api::CRITICAL_EMAIL));
				$this->errorHandler->add(ErrorHandler::API_INVALID_POST_REQUEST);
				$this->errorHandler->sendAllErrorsAndExit();
			case Api::REQUEST_TYPE_NORMAL:
				if(isset($this->request->special)) {
					// switch ($this->request->special) {


					// }
					
					echo "POST normal-special (??) not implemented.\nIn ApiPost ".__FUNCTION__." Line ".__LINE__."\n";
				} else {
					if($this->request->model->modifiedByField) {
						// default: 'modBy'
						$this->data->{$this->request->model->modelName}[$this->request->model->modifiedByField] = $this->account->id;
					}
					unset($this->data->{$this->request->model->modelName}['modDate']);

					$dataSet = (isset($this->data->{$this->request->model->modelName})) ? $this->data->{$this->request->model->modelName} : $this->data;

					$id = $this->request->model->add($dataSet);

					if($this->errorHandler->hasErrors()) {
						$this->errorHandler->sendAllErrorsAndExit();
					}
					$this->response->{$this->request->model->modelName} = $this->request->model->getOneById($id);
					$this->data->{$this->request->model->modelName}['id'] = $id;
					$this->log->write($this->account->id, 'create', $this->request->model->modelName, $this->data);
					#$this->response->{$this->request->model->modelNamePlural} = $this->request->model->getAll();
				}	
				break;
		}
		return $this->response;
	}

	public function postSpecial() {
		// echo "GET REQUEST_TYPE_SPECIAL: ".$this->request->special;
		switch ($this->request->special) {
			case "fileUpload":
				// var_dump($this->data);
				require_once("FileUpload.php");
				$fileUpload = new FileUpload($this->db, $this->ENV, $this->errorHandler, $this->log);
				$fileUpload->upload($this->data, $this->account);
				exit;
			case "task":
				require_once("TasksPrototype.php");
				// check if we have Task.php implemented
				if(!file_exists($this->ENV->dirs->appRoot."Tasks.php")) {
					$this->errorHandler->throwOne(ErrorHandler::TASK_NOT_DEFINED);
					exit;
				}
				require_once($this->ENV->dirs->appRoot."Tasks.php");
				$tasks = new \Jeff\Api\Tasks($this->db, $this->ENV, $this->errorHandler, $this->account, $this->log);
				
				if(isset($this->request->requestArray[1])) {
					// der task ist im request zb: task/addUserToWorkgroup (die Daten in postData)
					// check if we have a fitting method defined:
					$taskName = $this->request->requestArray[1];
				} elseif (isset($this->data->task)) {
					// ist der task woanders versteckt
					$taskName = $this->data;
				}
				if(method_exists($tasks, $taskName)){
					$response = $tasks->{$taskName}($this->data, $this->request);
				} else {
					$this->errorHandler->throwOne(ErrorHandler::TASK_NOT_DEFINED);
					exit;
				}
				return $response;
				break;
			case "signup":
			case "signin":
				// Name splitten
				if(isset($this->data->fullName) && !isset($this->data->firstName) && !isset($this->data->lastName)) {
					require_once("Names.php");
					$names = Names::Arrange($this->data->fullName);
					$this->data->firstName = $names[0];
					$this->data->middleName = $names[1];
					$this->data->prefixName = $names[2];
					$this->data->lastName = $names[3];
				}

				include_once($this->ENV->dirs->models."Accounts.php");
				$account = new Models\Account($this->db, $this->ENV, $this->errorHandler, null);
				if($account->signup($this->data)) {
					$response = $account->getAccount(); 
					return $response;
				} else {
					$this->errorHandler->add(array("Signup Error", "Could not sign up new account", 500, ErrorHandler::CRITICAL_EMAIL, false));
					$this->errorHandler->sendAllErrorsAndExit();
					exit;
				}
				break;
			default:
				$this->errorHandler->throwOne(ErrorHandler::API_INVALID_POST_REQUEST);
				exit;


		}
	}

}