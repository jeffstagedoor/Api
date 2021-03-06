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
use Jeff\Api\Request\RequestType;
use Jeff\Api\Utils\Names;

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
	/** @var object the request Object */
	private $request;
	/** @var \MySqliDb Instance of database class */
	private $db;
	/** @var Models\Account Instance of Account class */
	private $account;
	/** @var array array of items to add */
	private $items;
	/** @var object the response to be returned to client */
	private $response;

	/**
	 * The Constructor.
	 * Only sets the passed in instances/classes to private vars
	 * @param object         $request      The requst object
	 * @param \MySqliDb      $db           Instance of Database class
	 * @param Models\Account $account      Instance of Account
	 */
	function __construct($request, $db, $account) {
		$this->request = $request;
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
			case RequestType::REFERENCE: 
				$model = $this->request->model;
				$modelLeft = $this->request->modelLeft;
				#var_dump($this->request->data);
				#var_dump($this->request->data->{$model->modelName});
				$itemName = $modelLeft->modelNamePlural.'2'.$model->modelName;
				if(!isset($this->request->data->{$itemName})) {
					ErrorHandler::throwOne(41);
					exit;
				}
				$dataSet = $this->request->data->{$itemName};
				$dataSet['modBy'] = $this->account->id;
				unset($dataSet['modDate']);
				$id = $model->addMany2Many($modelLeft, $dataSet);
				
				if(ErrorHandler::hasErrors()) {
					ErrorHandler::sendAllErrorsAndExit();
				}

				$this->response->{$itemName} = $this->request->model->getMany2Many($id, $modelLeft->modelNamePlural);
				$this->request->data->{$itemName}['id'] = $id;
				Log::write($this->account->id, $itemName."Add", $itemName, $this->request->data);
				return $this->response;
				break;
			case RequestType::COALESCE:
				$items = $this->request->model->addMultiple($this->request->data->{$this->request->model->modelName}, $this->request->data->multipleParams);
				$this->response->{$this->request->model->modelNamePlural} = $items;
				$logData = new \stdClass();
				$logData->for = new LogDefaultFor(NULL,\Constants::USER_ADMIN,NULL,NULL,NULL,NULL,NULL,NULL);
				$logData->meta = new LogDefaultMeta(NULL,NULL, count($items),NULL, json_encode($this->request->data->multipleParams));
				Log::write($this->account->id, "createMultiple", $this->request->model->modelName, $logData);
				return $this->response;
				break;
			case RequestType::QUERY:
				ErrorHandler::add(array("API Error", "I received a Post request with a filter. Not implemented, doesn't make sense.",500,true, Api::CRITICAL_EMAIL));
				ErrorHandler::add(ErrorHandler::API_INVALID_POST_REQUEST);
				ErrorHandler::sendAllErrorsAndExit();
			case RequestType::NORMAL:
				if(isset($this->request->special)) {

					switch ($this->request->special) {
						case 'import':
							$this->response->{$this->request->model->modelName} = $this->request->model->import($this->request->data);
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
						$this->request->data->{$this->request->model->modelName}[$this->request->model->modifiedByField] = $this->account->id;
					}
					unset($this->request->data->{$this->request->model->modelName}['modDate']);

					$dataSet = (isset($this->request->data->{$this->request->model->modelName})) ? $this->request->data->{$this->request->model->modelName} : $this->request->data;
					$id = $this->request->model->add($dataSet);

					$this->response->{$this->request->model->modelName} = $this->request->model->getOneById($id);
					$this->request->data->{$this->request->model->modelName}['id'] = $id;
					Log::write($this->account->id, 'create', $this->request->model->modelName, $this->request->data);
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
				// var_dump($this->request->data);
				require_once("FileUpload.php");
				$fileUpload = new FileUpload($this->db);
				$fileUpload->upload($this->request->data, $this->account);
				exit;
			case "task":
				require_once("TasksPrototype.php");
				// check if we have Task.php implemented
				if(!file_exists(Environment::$dirs->appRoot."Tasks.php")) {
					ErrorHandler::throwOne(ErrorHandler::TASK_NOT_DEFINED);
					exit;
				}
				require_once(Environment::$dirs->appRoot."Tasks.php");
				$tasks = new \Jeff\Api\Tasks($this->db, $this->account);
				
				if(isset($this->request->params[1])) {
					// der task ist im request zb: task/addUserToWorkgroup (die Daten in postData)
					// check if we have a fitting method defined:
					$taskName = $this->request->params[1];
				} elseif (isset($this->request->data->task)) {
					// ist der task woanders versteckt
					$taskName = $this->request->data;
				}
				if(method_exists($tasks, $taskName)){
					$response = $tasks->{$taskName}($this->request);
				} else {
					ErrorHandler::throwOne(ErrorHandler::TASK_NOT_DEFINED);
					exit;
				}
				return $response;
				break;
			case "signup":
			case "signin":
				// Name splitten
				if(isset($this->request->data->fullName) && !isset($this->request->data->firstName) && !isset($this->request->data->lastName)) {
					require_once("Names.php");
					$names = Names::Arrange($this->request->data->fullName);
					$this->request->data->firstName = $names[0];
					$this->request->data->middleName = $names[1];
					$this->request->data->prefixName = $names[2];
					$this->request->data->lastName = $names[3];
				}

				include_once(Environment::$dirs->models."Accounts.php");
				$account = new Models\Account($this->db, $this->ENV, $this->errorHandler, null);
				if($account->signup($this->request->data)) {
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