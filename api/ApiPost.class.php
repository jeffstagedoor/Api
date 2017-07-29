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

require_once("../config.php");

// 
// GET
// 

Class ApiPost
{
	private $db;
	private $Account;
	private $request;
	private $ENV;
	private $items;

	function __construct($request, $data, $ENV, $db, $errorHandler, $Account=NULL) {
		// global $ENV;
		$this->request = $request;
		$this->data = $data;
		$this->ENV = $ENV;
		$this->db = $db;
		$this->errorHandler = $errorHandler;
		$this->Account = $Account;
	}

	public function postItem() {
		switch ($this->request->type) {
			case Api::REQUEST_TYPE_REFERENCE: 
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


					}
				} else {
					if($this->request->model->modifiedByField) {
						// default: 'modBy'
						$this->data->{$this->request->model->modelName}[$this->request->model->modifiedByField] = $this->Account->id;
					}
					unset($this->data->{$this->request->model->modelName}['modDate']);

					$dataSet = (isset($this->data->{$this->request->model->modelName})) ? $this->data->{$this->request->model->modelName} : $this->data;

					$id = $this->request->model->add($dataSet);
					if($this->errorHandler->hasErrors()) {
						$this->errorHandler->sendApiErrors();
						exit;
					}
					$this->item = $this->request->model->getOneById($id);
					$this->data->{$this->request->model->modelName}['id'] = $id;
					
					ApiHelper::writeLog($this->request->model->modelName, $this->data, 'create');

					$this->items = $this->request->model->getAll();
				}	
				break;
		}
		return $this->items;
	}

}