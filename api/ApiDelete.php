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
use Log\Log;

/**
*	Class ApiDelete
*	
*	@author Jeff Frohner
*	@copyright Copyright (c) 2017
*	@license   private
*	@version   1.8.0
*
**/
Class ApiDelete
{
	/** @var \MySqliDb Instance of database class */
	private $db;
	/** @var Models\Account Instance of Account class */
	private $account;
	/** @var object the request Object */
	private $request;
	/** @var array array of items to delete */
	private $items;

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
	 * - deleteMany2Many (a REQUEST_TYPE_REFERENCE)
	 * - delete (a REQUEST_TYPE_NORMAL) (this also sets the new sort if sorting is enabled on this model)
	 * 
	 * @return response-object leftOver items restricted by filter if model is sortable OR an empty object
	 */
	public function deleteItem() {
		switch ($this->request->type) {
			case Api::REQUEST_TYPE_REFERENCE: 
				$model = $this->request->model;
				$modelLeft = $this->request->modelLeft;
				if(!isset($this->request->id)) {
					ErrorHandler::throwOne(45);
					exit;
				}

				$id = $model->deleteMany2Many($modelLeft, $this->request->id);
				if($id) { 
					$response = new \stdClass();
					return $response;
				} else {
					ErrorHandler::throwOne(24);
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
				Log::write($this->account->id, 'delete', $this->request->model->modelName, $logData);
				
				break;
		}
		return $this->items;
	}

}