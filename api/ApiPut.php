<?php
/**
*	Class ApiPut
*	
*	@author Jeff Frohner
*	@copyright Copyright (c) 2017
*	@license   private
*	@version   1.0
*
**/
namespace Jeff\Api;

#require_once("../config.php");

// 
// GET
// 

Class ApiPut
{
	private $db;
	private $account;
	private $request;
	private $ENV;
	private $item;

	function __construct($request, $data, $ENV, $db, $errorHandler, $account, $log) {
		$this->request = $request;
		$this->data = $data;
		$this->ENV = $ENV;
		$this->db = $db;
		$this->errorHandler = $errorHandler;
		$this->account = $account;
		$this->log = $log;
		$this->item = new \stdClass();
	}

	public function putItem() {
		switch ($this->request->type) {
			case Api::REQUEST_TYPE_REFERENCE: 
				$model = $this->request->model;
				$modelLeft = $this->request->modelLeft;
				if(!isset($this->request->id)) {
					$this->errorHandler->throwOne(ErrorHandler::API_ID_MISSING);
					exit;
				}

				$dataSet = $this->data->{$modelLeft->modelNamePlural.'2'.$model->modelName};
				$dataSet['modBy'] = $this->account->id;
				unset($dataSet['modDate']);

				$success = $model->updateMany2Many($modelLeft, $this->request->id, $dataSet);
				
				if($success) { 
					$response = new \stdClass();
					$this->items = new \stdClass();
					$this->items->{$modelLeft->modelNamePlural.'2'.$model->modelName} = $this->request->model->getMany2Many($this->request->id, $modelLeft->modelNamePlural);
					return $this->items;
				} else {
					$this->errorHandler->add(ErrorHandler::DB_UPDATE);
					$this->errorHandler->throwOne(array('DB-Error', 'Could not updateMany2Many in '.__FILE__.': '.__LINE__.' with dbError: '.$this->db->getLastError(), $this->errorHandler::CRITICAL_EMAIL, true));
					exit;
				}
				
				
				break;

			// case Api::REQUEST_TYPE_COALESCE:
			// 	$this->items = $this->request->model->getCoalesce($this->data->ids);
			// 	break;
			// case Api::REQUEST_TYPE_QUERY:
			// 	$filter = $this->_getFilter();
			// 	$this->items = $this->request->model->getAll($filter);
			// 	break;
			case Api::REQUEST_TYPE_NORMAL:
				if(isset($this->request->special)) {
					switch ($this->request->special) {
						case "sort":
							if($this->request->model->isSortable) {
								// method sort returns ALL items in the reference
								$items = $this->request->model->sort($this->data->reference, $this->data->id, $this->data->direction, $this->data->currentSort);
								if($items) {
									$response = new \stdClass();
									$response->{$this->request->model->modelNamePlural} = $items;
									
									$logData = new \stdClass();
									$logData->for = new Log\LogDefaultFor(NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL);
									$logData->meta = new Log\LogDefaultMeta($this->data->id,$this->data->reference, $this->data->currentSort,$this->data->direction, $this->request->model->sortBy);
									$this->log->write($this->account->id, 'sort', $this->request->model->modelName, $logData);
									return $response;
								} else {
									$this->errorHandler->sendApiErrors();
									exit;
								}
							} else {
								$this->errorHandler->throwOne(ErrorHandler::MODEL_NOT_SORTABLE);
								exit;
							}
							break;
						default:
							/*
							*	here we check if there is a special method implemented in the current model.
							*	but we need to make sure, that no unintended methods get called, so we introduce
							*	a property 'specialMethods' to the model. Unless this method is listed there it wont be called. 
							*/
							if(!in_array($this->request->special, $this->request->model->specialMethods)) {
								$this->errorHandler->throwOne(array("SEC ALERT", "a un-registered special method was tried to be called via ApiPut: ".$this->request->special,500, ErrorHandler::CRITICAL_EMAIL, true));
								return false;
								exit;
							}
							$methodExists = method_exists($this->request->model, $this->request->special);
							$return = $this->request->model->{$this->request->special}($this->data, $this->account, $this->request);
							if(isset($return->log)) {
								$this->log->write($return->log->account, $return->log->type, $return->log->item, $return->log->data);
								unset($return->log);
							}
							return $return;
					}
				} else {
					if($this->request->model->modifiedByField) {
						// default: 'modBy'
						$this->data->{$this->request->model->modelName}[$this->request->model->modifiedByField] = $this->account->id;
					}
					unset($this->data->{$this->request->model->modelName}['modDate']);

					$dataSet = (isset($this->data->{$this->request->model->modelName})) ? $this->data->{$this->request->model->modelName} : $this->data;

					$updateReturn = $this->request->model->update($this->request->id, $dataSet);

					if($this->errorHandler->hasErrors()) {
						$this->errorHandler->sendApiErrors();
						$this->errorHandler->sendErrors();
					}
					if($updateReturn) {
						$this->item->{$this->request->model->modelName} = $this->request->model->getOneById($updateReturn->id);
						$this->data->{$this->request->model->modelName}['id'] = $updateReturn->id;
						#echo "data var_dumped in ApiPut Line 117:\n";
						#var_dump($this->data);
						$this->log->write($this->account->id, 'update', $this->request->model->modelName, $this->data);
					}
				}	
				break;
		}
		return $this->item;
	}

	public function putSpecial($modelsAll) {
		switch ($this->request->special) {
			case 'sort':
				echo "put special 'sort' is DEPRECATED. use api/modelNamePlural/sort";
				break;
			case 'changePassword':
				#debug("in change password",__FILE__,__LINE__,get_class($this));
				$auth = $this->account->verifyCredentials($this->data->email, $this->data->password);
				if($auth->success) {
					#debug("auth was successfull",__FILE__,__LINE__,get_class($this));
					if($this->account->comparePasswords($this->data->passwordNew)) {
						$this->errorHandler->throwOne(ErrorHandler::AUTH_PWD_NOT_VALID);
						exit;
					}
					$pattern = '/([a-zA-Z0-9@!§$%=?+*#]{8,100})/';
					if(preg_match($pattern, $this->data->passwordNew)) {
						if($this->data->passwordNew===$this->data->passwordConfirm) {
							$this->account->changePassword($auth->user['id'], $this->data->passwordNew);
							$response = "{\"success\": {\"msg\": \"password changed\"} }";
							ApiHelper::sendResponse(200,$response);
							$this->log->write($this->account->id, 'changePassword', 'account', $this->data);
							exit;
						} else {
							$this->errorHandler->throwOne(ErrorHandler::AUTH_PWD_NOT_MATCHING);
							exit;
						}
					} else {
						$this->errorHandler->throwOne(ErrorHandler::AUTH_PWD_NOT_VALID);
						exit;
					}
				} else {
					// if oldPassword doesnt match the saved one, send invalid grant error
					$this->errorHandler->throwOne(ErrorHandler::AUTH_PWD_INCORRECT);
					exit;
				}
				exit;
			default:
				# code...
				break;
		}
	}

}