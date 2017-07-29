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

require_once("../config.php");

// 
// GET
// 

Class ApiDelete
{
	private $db;
	private $Account;
	private $request;
	private $ENV;
	private $items;

	function __construct($request, $data, $ENV, $db, $errorHandler) {
		// global $ENV;
		$this->request = $request;
		$this->data = $data;
		$this->ENV = $ENV;
		$this->db = $db;
		$this->errorHandler = $errorHandler;
	}

	public function deleteItem() {
		switch ($this->request->type) {
			case Api::REQUEST_TYPE_REFERENCE: 
				echo "not implemented yet";
				break;
			case Api::REQUEST_TYPE_NORMAL:
				$item = $this->request->model->delete($this->request->id);
				// if the model is a sortable, update sort of the related items here
				// tried it via a trigger, that won't work (cant update the same table twice); only solution in db would be a stored procedure.
				// I don't know how to do that, so I'll do it here for now:
				
				if($this->request->model->isSortable) {
					$sql = "Update ".$this->request->model->getDbTable()." set sort = sort-1 where sort>".$item['sort']." and ".$this->request->model->sortBy."=".$item[$this->request->model->sortBy];
					$this->db->rawQuery($sql);
					// get the left over items to send back to frontend:
					$this->items = $this->request->model->getAll();
					// ApiHelper::postItems($this->request->model, $this->items);
				} else {
					echo "{}";
					exit;
				}
				ApiHelper::writeLog($this->request->model->modelName, $this->data, 'delete');
				break;
		}
		return $this->items;
	}

}