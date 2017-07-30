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

	function __construct($request, $data, $ENV, $db, $errorHandler, $Account, $log=NULL) {
		$this->request = $request;
		$this->data = $data;
		$this->ENV = $ENV;
		$this->db = $db;
		$this->errorHandler = $errorHandler;
		$this->Account = $Account;
		if($log===NULL) {
			require_once("Log.php");
			$log = new Log($this->db, $this->ENV, $this->errorHandler);
		}
		$this->log = $log;
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
						$this->errorHandler->sendErrors();
					}
					$this->item = $this->request->model->getOneById($id);
					$this->data->{$this->request->model->modelName}['id'] = $id;
					$this->log->write($this->Account->id, 'create', $this->request->model->modelName, $this->data);
					$this->items = $this->request->model->getAll();
				}	
				break;
		}
		return $this->items;
	}

}