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
use Jeff\Api\Log\Log;

/**
*	Class that handles POST requests.
*	
*	@author Jeff Frohner
*	@copyright Copyright (c) 2017
*	@license   private
*	@version   1.0
*
**/
Class ApiPost
{
	/** @var \MySqliDb Instance of database class */
	private $db;
	/** @var Models\Account Instance of Account class */
	private $account;
	/** @var object the request Object */
	private $request;
	/** @var array array of items to add */
	private $items;
	/** @var object the response to be returned to client */
	private $response;

	/**
	 * The Constructor.
	 * Only sets the passed in instances/classes to private vars
	 * @param object         $request      The requst object
	 * @param object         $data         The data with the item to add
	 * @param \MySqliDb      $db           Instance of Database class
	 * @param Models\Account $account      Instance of Account
	 */
	function __construct($request, $data, $db, $account) {
		$this->request = $request;
		$this->data = $data;
		$this->db = $db;
		$this->account = $account;
	}

	/**
	 * Calls the matching methods in (extending) Model-class.
	 *
	 * Depending on request type this prepares for and calls either
	 * - addMany2Many (a REQUEST_TYPE_REFERENCE)
	 * - addMultiple (a REQUEST_TYPE_COALESQUE)
	 * - add (a REQUEST_TYPE_NORMAL)
	 * - special methods (a REQUEST_TYPE_NORMAL with a request->special set, which is an api call to api/modelName/import f.e.)
	 * 
	 * @return response-object
	 */
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
					ErrorHandler::throwOne(41);
					exit;
				}
				$dataSet = $this->data->{$itemName};
				$dataSet['modBy'] = $this->account->id;
				unset($dataSet['modDate']);
				$id = $model->addMany2Many($modelLeft, $dataSet);
				
				if(ErrorHandler::hasErrors()) {
					ErrorHandler::sendAllErrorsAndExit();
				}

				$this->response->{$itemName} = $this->request->model->getMany2Many($id, $modelLeft->modelNamePlural);
				$this->data->{$itemName}['id'] = $id;
				Log::write($this->account->id, $itemName."Add", $itemName, $this->data);
				return $this->response;
				break;
			case Api::REQUEST_TYPE_COALESCE:
				$items = $this->request->model->addMultiple($this->data->{$this->request->model->modelName}, $this->data->multipleParams);
				$this->response->{$this->request->model->modelNamePlural} = $items;
				$logData = new \stdClass();
				$logData->for = new LogDefaultFor(NULL,\Constants::USER_ADMIN,NULL,NULL,NULL,NULL,NULL,NULL);
				$logData->meta = new LogDefaultMeta(NULL,NULL, count($items),NULL, json_encode($this->data->multipleParams));
				Log::write($this->account->id, "createMultiple", $this->request->model->modelName, $logData);
				return $this->response;
				break;
			case Api::REQUEST_TYPE_QUERY:
				ErrorHandler::add(array("API Error", "I received a Post request with a filter. Not implemented, doesn't make sense.",500,true, Api::CRITICAL_EMAIL));
				ErrorHandler::add(ErrorHandler::API_INVALID_POST_REQUEST);
				ErrorHandler::sendAllErrorsAndExit();
			case Api::REQUEST_TYPE_NORMAL:
				if(isset($this->request->special)) {

					switch ($this->request->special) {
						case 'import':
							$this->response->{$this->request->model->modelName} = $this->request->model->import($this->data);
							Log::write($this->account->id, 'create', $this->request->model->modelName, $this->response->{$this->request->model->modelName});
							break;
						default:
							ErrorHandler::throwOne(array("Api Error", "POST normal-special '{$this->request->special}'' not implemented.\nIn ApiPost ".__FUNCTION__." Line ".__LINE__."\n", 500, ErrorHandler::CRITICAL_EMAIL, true));
							ErrorHandler::throwOne(array("Api Error", "POST normal-special '{$this->request->special}'' not implemented.", 500, ErrorHandler::CRITICAL_EMAIL, false));
							exit;
					}

				} else {
					if($this->request->model->modifiedByField) {
						// default: 'modBy'
						$this->data->{$this->request->model->modelName}[$this->request->model->modifiedByField] = $this->account->id;
					}
					unset($this->data->{$this->request->model->modelName}['modDate']);

					$dataSet = (isset($this->data->{$this->request->model->modelName})) ? $this->data->{$this->request->model->modelName} : $this->data;

					$id = $this->request->model->add($dataSet);

					if(ErrorHandler::hasErrors()) {
						ErrorHandler::sendAllErrorsAndExit();
					}
					$this->response->{$this->request->model->modelName} = $this->request->model->getOneById($id);
					$this->data->{$this->request->model->modelName}['id'] = $id;
					Log::write($this->account->id, 'create', $this->request->model->modelName, $this->data);
					#$this->response->{$this->request->model->modelNamePlural} = $this->request->model->getAll();
				}	
				break;
		}
		return $this->response;
	}

	/**
	 * An api-call with a special verb as first verb instead of a model name.
	 * The special verbs are defined in Api-class
	 * @return response-object
	 */
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
					ErrorHandler::throwOne(ErrorHandler::TASK_NOT_DEFINED);
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
					ErrorHandler::throwOne(ErrorHandler::TASK_NOT_DEFINED);
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
					ErrorHandler::add(array("Signup Error", "Could not sign up new account", 500, ErrorHandler::CRITICAL_EMAIL, false));
					ErrorHandler::sendAllErrorsAndExit();
				}
				break;
			case "import":
				echo "importing! NOT IMPLEMENTED HERE, BUT IN POST ..api/modelName/import";
				break;
			default:
				ErrorHandler::throwOne(ErrorHandler::API_INVALID_POST_REQUEST);
				exit;
		}
	}

}