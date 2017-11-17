<?php
/**
*	Class ApiDelete
*	
*	@author Jeff Frohner
*	@copyright Copyright (c) 2017
*	@license   private
*	@version   1.0
*
**/
namespace Jeff\Api;

// 
// DELETE
// 

Class ApiDelete
{
	private $db;
	private $account;
	private $request;
	private $ENV;
	private $items;
	private $log;

	function __construct($request, $data, $ENV, $db, $errorHandler, $account, $log) {
		$this->request = $request;
		$this->data = $data;
		$this->ENV = $ENV;
		$this->db = $db;
		$this->errorHandler = $errorHandler;
		$this->account = $account;
		$this->log = $log;
	}

	public function deleteItem() {
		switch ($this->request->type) {
			case Api::REQUEST_TYPE_REFERENCE: 
				$model = $this->request->model;
				$modelLeft = $this->request->modelLeft;
				if(!isset($this->request->id)) {
					$this->errorHandler->throwOne(45);
					exit;
				}

				$id = $model->deleteMany2Many($modelLeft, $this->request->id);
				if($id) { 
					$response = new \stdClass();
					return $response;
				} else {
					$this->errorHandler->throwOne(24);
					exit;
				}

				break;
			case Api::REQUEST_TYPE_NORMAL:
				$item = $this->request->model->get($this->request->id);
				$success = $this->request->model->delete($this->request->id);

				// if the model is a sortable, update sort of the related items here
				if($this->request->model->isSortable) {
					$sql = "Update ".$this->request->model->getDbTable()." set sort = sort-1 where sort>".$item['sort']." and ".$this->request->model->sortBy."=".$item[$this->request->model->sortBy];
					$this->db->rawQuery($sql);
					// get the left over items to send back to frontend:
					$filters = Array();
					$filters[] = Array("key"=>$this->request->model->sortBy, "value"=>$item[$this->request->model->sortBy], "comp"=>"=");
					$this->items = new \stdClass();
					$this->items->{$this->request->model->modelNamePlural} = $this->request->model->getAll($filters);
				} else {
					$response = new \stdClass();
					return $response;
				}
				$logData = new \stdClass();
				$logData->{$this->request->model->modelName} = $item;
				$this->log->write($this->account->id, 'delete', $this->request->model->modelName, $logData);
				
				break;
		}
		return $this->items;
	}

}