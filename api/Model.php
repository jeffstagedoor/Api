<?php
/**
*	Class Model
*	this is the basic Class for all models
*	includes getters and setters as far as they can be generalized
*	
*	@author Jeff Frohner
*	@copyright Copyright (c) 2015-2016
*	@license   private
*	@version   1.8
*
**/
namespace Jeff\Api\Models;
use Jeff\Api as Api;



Class Model {

	// a modelName MUST NOT include a '2', because otherwise it'll be treated as a relational-model
	public $modelName = null;
	public $modelNamePlural = null;
	public $motherModel = null; // this will be needed to make a bubbleUp method, 
								// to determine all the mother-models of a grand-child model such as a track, 
								// that belongsTo an artistgroup, an production, a workgroup
								// will be needed to figure out if a user may edit/delete an item or not
	
	public $modelFields = array();  // DEPRECATED, use dbDefinition istead!
	public $dbDefinition = array( 	
			/*
			array(
				'id',				// name of column
				'int',				// data type of column (int, varchar, date, timestamp, text, ..)
				'11',				// length of column (empty for date, text)
				false,				// if column can be NULL
				null, 				// default value. if NULL is wanted, write 'NULL' (as a string)
				'AUTO_INCREMENT'	// extras like auto_increment
		    	),
		    */
			);
	public $modifiedByField = null;
	// Models with isSortable=true MUST have a field 'sort' in dbTable
	public $isSortable = false;
	public $sortBy = 'referenceField';


	// many2many-references often have a value that describe that relationship
	// this could be role or rights
	public $many2manyParam = 'role';


	// DB-vars & Object
	protected $db = null;
	protected $dbIdField = 'id';
	protected $dbTable = 'undefined';
	protected $cols = null;

	public $errors = Array();	// DEPRECATED -> use ErrorHandler Class instead
	public $lastInsertedID = -1;

	public $sideload = null;
	protected $sideLoadTargetsSave = Array();

	// maskFields
	// these fields will be masked with *** according to it's properties
	protected $maskFields = Array(
		// 'email' => myStagedoor\DataMasker::MASK_TYPE_EMAIL,
		// 'tel' => myStagedoor\DataMasker::MASK_TYPE_TEL,
		);

	// searchSendCols
	// what data (=db-fields) to send when querying a search 
	protected $searchSendCols = Array('id');	

	// hiddenProperties
	// what properties will be unset before sending the payload
	// allways remove password, authToken, auth, ...
	protected $hiddenProperties = Array('password', 'authToken', 'auth');

	// validateFields
	// what fields shall be validated before inserting/updating
	protected $validateFields = Array(
		// Array('field' => 'email', 'valtype' => myStagedoor\Validate::VAL_TYPE_EMAIL, 'arg' => null),
		// Array('field' => 'description', 'valtype' => myStagedoor\Validate::VAL_TYPE_LENGTH_MAX, 'arg' => 300),
		// Array('field' => 'description', 'valtype' => myStagedoor\Validate::VAL_TYPE_LENGTH_MIN, 'arg' => 300),
		);

	// hasMany-Fields
	protected $hasManyFields = Array (
			// Array("name"=>'user2workgroups',"dbTable"=>'user2workgroup', "dbTargetFieldName"=>'workgroup', "dbSourceFieldName"=>'user', "saveToStoreField"=>'workgroup', "saveToStoreName"=>'workgroups'),
		);

	// what Items to send as sideload when doing a simple getOneById()
	protected $sideloadItems = Array( 
			// Array("name"=>'user2workgroups',"dbTable"=>'user2workgroup', "reference"=>'user2workgroups'),
			// Array("name"=>'workgroups', "dbTable"=>'workgroups', "reference"=>'workgroups'),
			// Array("name"=>'user2productions', "dbTable"=>'user2production', "reference"=>'user2productions'),
			// Array("name"=>'productions', "dbTable"=>'productions', "reference"=>'productions'),
		);


	// CONSTANTS
	const SEARCH_TYPE_STRICT = "strict";
	const SEARCH_TYPE_SEMISTRICT = "semistrict";
	const SEARCH_TYPE_LOOSE = "loose";
	const SEARCH_TYPE_VERYLOOSE = "veryloose";
	const SEARCH_MIN_LENGTH = 4;


	// CONSTRUCTOR
	// always pass the fitting db-object to contructor
	public function __construct($db=NULL) 
	{
		$this->db = $db;

		// modelFields will get Deprecated, this is for intermediate.
		// old Version had a plain array in modelFields with all the ColumnNames.
		// new Version has dbDefinition, which will be used instead.
		if(isset($this->modelFields) && !isset($this->dbDefinition)) {
			echo "updating dbDefinition in Model::construct - This is DEPRECATED, modelFields should be transformed to dbDefinition.";
			$this->dbDefinition  = array(); 
			foreach ($this->modelFields as $i => $def) {
				$this->dbDefinition[][0] = $def;
			}
		}
	}

	public function hasErrors() 
	{
		if(sizeof($this->errors)>0) {
			return true;
		} else {
			return false;
		}
	}

	public function getErrors()
	{
		return $this->errors;
	}



	//
	// STANDARD GETTERS
	//

	/*
	*	method get()
	*	alias for getOneById(id) and getAll()
	*/
	public function get($id=0) 
	{
		if($id>0) {
			$items = $this->getOneById($id);
		} else {
			$items = $this->getAll();
		}
		return $items;
	}

	/*
	*	method getOneById()
	*	
	*/
	public function getOneById($id) 
	{
		$this->db->where($this->dbIdField,$id);
		$item = $this->db->getOne($this->dbTable);
		if($item) {
			$item = $this->_unsetHiddenProperties($item);
			$item = $this->_addHasMany($item, $id); 	// add hasMany-Relationships, defined in Child-Model Class as $hasManyFields
			$this->_addSideloads($item); 			// add sideloads, defined in Child-Model Class as $sideloadItems

			return $item;
		} else {
			return null;
		}
	}



	/*
	*	method getAll()
	*	
	*/
	public function getAll($filters=null) 
	{
		$this->cols = $this->_makeAssociativeFieldsArray($this->dbTable, $this->dbDefinition);

		// check if the child-model has the beforeGetAll-Hook implemented
		// Standard Example for such a hook: 
		// 
		// 	 function beforeGetAll() {
		// 		global $Account;
		// 		$restriction = new ModelRestriction('REF_ID', 'account', $Account->id);
		// 		$restrictions = Array();
		// 		$restrictions[] = $restriction;
		// 		return $restrictions;
		//   }
		//

		if(method_exists($this, 'beforeGetAll')) {

			// if so, get the restrictions
			$restrictions = $this->beforeGetAll();
			// print_r($restrictions);
			// and apply them, depending on their type
			foreach ($restrictions as $key => $restriction) {
				switch ($restriction->type) {
					case 'ID_IN':
						// the model describes a list of id's that shall be returned. Usually set by the Account
						$this->db->where($this->dbTable.'.'.$this->dbIdField, $restriction->data, "IN");

						break;
					case 'REF_IS':
						// the model describes a definite reference the model shall be limited to.
						$this->db->where($restriction->referenceField, $restriction->id);
						// echo "REF_IS";
						break;
					case 'REF_IN':
						// the model describes a list of referenced id's the model shall be limited to.
						$this->db->where($restriction->referenceField, $restriction->data, "IN");
						break;

					case 'LIMIT':
						// to be done....
						break;
					default:
						break;
				}
			}

		}

		if(is_array($filters)) {
			// if we gt a filter passed, set them...
			// filter will come as an array: [{key: 'nameofthefield', value: 'testvalue', comp: '='}]
			foreach ($filters as $filter) {
				$this->db->where($this->dbTable.'.'.$filter['key'], $filter['value'], $filter['comp']);
			}
		}

		$result = $this->_getResultFromDb();
		// echo $this->db->getLastQuery();
		$items = $this->_getHasMany($result);

		return $items;
	}


	/**
	*	method _getHasMany($ids)
	*
	*	@param items (Array) the main result the hasMany Items shall be added to
	*	@param ids (Array) ids of the main result
	*	@return (Array) items
	**/
	private function _getHasMany($items) {
		if(count($this->hasManyFields)===0) {
			// if there are no hasManyFields to get, just return what you have gotten....
			return $items;
		}
		
		// walk all the items, save them ids in an array, do select with 'IN', walk the items again to add the hasManyFields
		if(!count($items)) { // no items -> so we don't need to walk them, just return the empty array of items
			return $items;
		} else {
			$ids = array();
			foreach ($items as $item) {
				$ids[]=$item[$this->dbIdField];
			}
		}

		// now we have all the ids of the main items
		foreach ($this->hasManyFields as $hmf) {
			$hmf['ids'] = array();
			$this->db->where($hmf['dbSourceFieldName'], $ids, 'IN');
			$hasMany = $this->db->get($hmf['dbTable'], null, array('id', $hmf['dbSourceFieldName']));
			foreach ($hasMany as $hasManyItem) {
				foreach ($items as &$item) {
					if(!isset($item[$hmf['name']])) {
						$item[$hmf['name']] = array();
					}
					if($item['id']===$hasManyItem[$hmf['dbSourceFieldName']]) {
						array_push($item[$hmf['name']], $hasManyItem['id']);
					}
				}
			}
		}
		return $items;
	}


	/**
	*	method getCoalesce($coalesceIds)
	*   if we get an array of ids, as they arrive when doing an coalesceFindRecord call (in ember RestAdapter 'coalesceFindRequests: true')
	*   we return only the corresponding items 
	*	
	*	@param coalesceIds (Array)
	**/
	public function getCoalesce($coalesceIds=null) {
		$this->cols = $this->_makeAssociativeFieldsArray($this->dbTable, $this->dbDefinition);
	
		if($coalesceIds) {
			$this->db->where($this->dbTable.'.id', $coalesceIds,'IN');
			$items = $this->_getResultFromDb();
			$items = $this->_getHasMany($items);
			return $items;
		} else {
			$err->add(42);
			$this->errors[] = "API-Error: getCoalesce without sending coalesceIds";
			return false;
		}
	}


	/*
	* private function _getResultFromDb()
	*/
	private function _getResultFromDb() {
		global $err;
		$result = null;
		try {
			$result = $this->db->get($this->dbTable, null, $this->cols);
		} 
		catch (Exception $e) {
			$err->add(20);
			$this->errors[] = "Database-Error: " .$this->db->getLastError();
		}
		return $result;
	}



	/*
	/	MANY TO MANY Relationships
	/
	/ this depends on following conventions:
	/ db-tables/models that represent a manyToMany Relationship have this name/structure:
	/ - dbTable= 'needle2haystack'; e.g. 'user2workgroup' (both singular)
	/ - id-field = needle_id + '_' + haystack_id (eg 2_15)
	/
	/
	/
	/
	*/
	public function getMany2Many($id, $what='user', $by='id') {
		if(is_array($id)) {
			// then it's likely a coalesceFindrecord call,
			// so we should return all items where id in that array
			$this->db->where($by, $id, 'IN');
		} else {
			// normal singular request or referential request 'by'
			$this->db->where($by, $id);
		}
		$many2many = $this->db->get($what.'2'.$this->modelName);
		if(count($many2many)===1 && $by==='id') {
			// one item found (and requested)
			return $many2many[0];
		} elseif (count($many2many)>1) {
			return $many2many;
		} else {
			// nothing found, return empty array
			return Array();
		}
	}


	// possible HOOK:
	// public function beforeUpdateMany2Many($id, $what, $data) {
	// 	return $data;
	// }
	public function updateMany2Many($id, $what, $data) {
		// call the hook beforeUpdateMany2Many if existing:
		if(method_exists($this, 'beforeUpdateMany2Many')) {
			$data = $this->beforeUpdateMany2Many($id, $what, $data);
		}

		$this->db->where('id', $id);
		$success = $this->db->update($what.'2'.$this->modelName, $data);
		return ($success) ?  $id : false;
	}


	// possible HOOK:
	// public function beforedeleteMany2Many($id, $what) {
	// 	return $true;
	// }
	public function deleteMany2Many($id, $what) {
		// call the hook beforeDeleteMany2Many if existing:
		if(method_exists($this, 'beforeUpdateMany2Many')) {
			$bool = $this->beforeUpdateMany2Many($id, $what);
		} else {
			$bool = true;
		}
		if($bool) {
			$this->db->where('id', $id);
			$success = $this->db->delete($what.'2'.$this->modelName);
			return ($success) ? $id : false;
		}
		return false;
	}



	/*
	*	method getCount()
	*	
	*/
	public function getCount($delimiters=null) 
	{
		// NEED TO WORK ON THAT!!!!!!
		if(is_object($delimiters)) {
			foreach ($delimiters as $key => $value) {
				$this->db->where($key, $value);
			}
		} elseif(is_array($delimiters)) {
			for ($i=0; $i < count($delimiters); $i++) { 
				$this->db->where($delimiters[$i]['key'], $delimiters[$i]['value']);
			}
		}
		$count = $this->db->getValue($this->dbTable, "count(*)");
		#echo $this->db->getLastQuery();
		return $count;
	}

	public function getLastInsertedId() 
	{
		return $this->lastInsertedID;
	}



	/*
	*	method search()
	*	
	*/
	public function search($data) 
	{
		if(isset($data->condition) && $data->condition==='or') {
			$or = true;
			unset($data->condition);
		} else {
			unset($data->condition);
		}
		$cnt=0;

		$searchType = $data->searchType;
		#echo "searchType: ".$searchType."\n";
		unset($data->searchType);
		switch ($searchType) {
			case self::SEARCH_TYPE_STRICT:
				$operator = "=";
				break;
			case self::SEARCH_TYPE_SEMISTRICT:
				$operator = "LIKE";
				break;
			case self::SEARCH_TYPE_LOOSE:
				$operator = "LIKE";
				break;
			case self::SEARCH_TYPE_VERYLOOSE:
				$operator = "LIKE";
				break;
			default:
				$operator = "=";
		}
		#echo "operator: ".$operator."\n";

		foreach ($data as $key => $value) {
			// for security, I first replace any placeholder with a questionmark
			// if not, a user could search for "%%%%" and would get all datasets...we dont want that
			$value=preg_replace('/[%*]/', "", $value);
			// still, if we have less then const::SEARCH_MIN_LENGTH letters without the special characters,
			// abort transforming to loose.... (otherwise a user could search for '------') and still get all datasets
			$test = preg_replace('/[ -_\´\`\']/', "%", $value);
			if(strlen($test) < $this::SEARCH_MIN_LENGTH) {
				$searchType=self::SEARCH_TYPE_STRICT;
			}
			if($searchType === self::SEARCH_TYPE_LOOSE) {
				$value=preg_replace('/[ -_\´\`\']/', "%", $value);
			}
			if($searchType === self::SEARCH_TYPE_VERYLOOSE) {
				$value=preg_replace('/[ -_\´\`\']/', "%", $value);
				$value='%'.$value.'%';
			}			
			if($cnt && $or) {
				$this->db->orWhere($key, $value, $operator);
			} else {
				$this->db->where($key, $value, $operator);
			}
			$cnt++;
		}
		if($cnt) { // if I have minimum one search item, give a result
			$result = $this->db->get($this->dbTable, NULL, $this->searchSendCols);
			#echo $this->db->getLastQuery();
			// Mask properties/fields that where defined to be masked in the Model ('maskFields')
			for ($i=0; $i <count($result) ; $i++) {
				foreach ($this->maskFields as $field => $type) {
					if(isset($result[$i][$field])) {
						$result[$i][$field] = myStagedoor\DataMasker::mask($result[$i][$field], $type);
					}
				}
			}
		} else { // else return nothing...
			$result = null;
		}
		return $result;
	}



	//
	// SETTERS
	//

	/*
	*	method add()
	*	
	*/
	public function add($data) 
	{
		global $err;

		// call the hook beforeAdd if existing:
		if(method_exists($this, 'beforeAdd')) {
			$data = $this->beforeAdd($data);
		}

		if($this->isSortable) {
			// if this Model is Sortable, it MUST have a field 'sort'
			// if no value came in, set 'sort' max+1
			// we MUST have a property called 'sortBy', which is the reference in which it should be sorted

			// first check if we have a value for sort:
			if(isset($data['sort']) && !is_null($data['sort'])) {
				// then we need to shift all the records after that by one
				$sql = 'Update '.$this->dbTable.' set sort=sort+1 where sort>=? AND '.$this->sortBy.'=?'; 
				$params = Array($data['sort'], $data[$this->sortBy]);
				$this->db->rawQuery($sql, $params);
			} else {
				// then we set this new item at the end of the sort list
				// so we need the max of sort:
				$sql = 'Select max(sort) max from '.$this->dbTable.' where '.$this->sortBy.'=?'; 
				$params = Array($data[$this->sortBy]);
				$max = $this->db->rawQueryValue($sql, $params);
				$data['sort'] = intval($max[0])+1;
			}
		}
		// unsetting hasMany-Fields that might come from Ember via POST:
		foreach ($this->hasManyFields as $hmf) {
			unset($data[$hmf['name']]);
		}

		// check if we have all data we need:
		$required = Api\ApiHelper::getRequiredFields($this->dbDefinition);
		$missingFields = Api\ApiHelper::checkRequiredFieldsSet($required, $data);
		// print_r($missingFields);
		if(count($missingFields)===0) {
			// the actual insert into database:
			$id = $this->db->insert($this->dbTable, $data);
			if($this->db->getLastError()>'') { 
				$err->add(new Api\Error($err::DB_INSERT, $this->db->getLastError()));
				return false;
			}
			$this->lastInsertedID = $id;
			return $id;
		} else {
			$e = Array("API-Error", "Not all required fields were sent. I missed: ".implode(",", $missingFields), 1, false);
			$err->add(new Api\Error($e));
			return false;
		}
	}


	/*
	*	method addMany2Many()
	*	
	*/

	// possible HOOK:
	// public function beforeAddMany2Many($what, $data) {
	// 	return $data;
	// }
	public function addMany2Many($what, $data) {

		$id = $data[$what] . '_' . $data[$this->modelName];
		$data['id'] = $id;
		// check if the artist is already Member (even with other role)
		$this->db->where('id', $id);
		$result = $this->db->getOne($what.'2'.$this->modelName);

		if($result) {
			if(isset($data[$this->many2manyParam]) && $result[$this->many2manyParam]!=$data[$this->many2manyParam]) {	// artist had other role then
				$udData = Array(
					$this->many2manyParam => $data[$this->many2manyParam],
					"modBy" => $data['modBy']
					);

				// call the hook beforeUpdateMany2Many if existing:
				if(method_exists($this, 'beforeUpdateMany2Many')) {
					$udData = $this->beforeUpdateMany2Many($id, $what, $udData);
				}

				// update in database
				$this->db->where('id', $id);
				$this->db->update($what.'2'.$this->modelName, $udData);
				if($this->db->count) { return $id; }
				else { return false; }
			} else {
				// artist was connected with same role already
				return $result['id'];
			}
		}
		// call the hook beforeAddMany2Many if existing:
		if(method_exists($this, 'beforeAddMany2Many')) {
			$data = $this->beforeAddMany2Many($what, $data);
		}
		// artist/user was not connected to this production/track/whatever yet, so insert him:
		$success = $this->db->insert($what.'2'.$this->modelName, $data);
		return ($success) ?  $id : false;
	}

	/*
	* 	method addMultiple()
	*	needs function addMultipleHook implemented in childModel
	*
	*/
	public function addMultiple($data, $multipleParams) 
	{
		global $err;

		// call the hook beforeAdd if existing:
		if(method_exists($this, 'beforeAddMultiple')) {
			$data = $this->beforeAddMultiple($data, $multipleParams);
		}

		// unsetting hasMany-Fields that might come from Ember via POST:
		foreach ($this->hasManyFields as $hmf) {
			unset($data[$hmf['name']]);
		}


		if(method_exists($this, 'addMultipleHook')) {
			$insertedIds = $this->addMultipleHook($data, $multipleParams);
		} else {
			$err->add(41);
			$this->errors[] = "No method addMultipleHook implemented for this itemType";
			throw new Exception("Error: No method addMultipleHook implemented", 1);
		}

		// now return all newly inserted events/items:
		if(count($insertedIds)) {
			$this->db->where($this->dbTable.'.'.$this->dbIdField, $insertedIds, "IN");
			$result = $this->_getResultFromDb();
			$items = $this->_getHasMany($result);
		} else {
			$items = array();
		}
		return $items;
	}


	public function update($id, $data) 
	{
		global $err;

		// call the hook beforeUpdate if existing:
		if(method_exists($this, 'beforeUpdate')) {
			$data = $this->beforeUpdate($id, $data);
		}

		// I should check here if the user MAY update that model at all!?
		if(is_object($data)) {
			$data = (Array) $data;
		}
		// unsetting hasMany-Fields that might come from Ember via POST:
		foreach ($this->hasManyFields as $hmf) {
			unset($data[$hmf['name']]);
		}


		$this->db->where($this->dbIdField, $id);
		// print_r($data);
		if ($this->db->update($this->dbTable, $data)) {
			// var_dump($data['invoiceDate']);
			// echo "\n\nRows Updated: ".$this->db->count;
			// echo "\n\n".$this->db->getLastQuery();
			return $this->db->count; // $db->count.' records were updated';
		} else {
			// echo "im else";
			// echo 'update failed: ' . $this->db->getLastError();
			if($this->db->getLastError()>'') { 
				$err->add(22);
				$this->errors[] = "Database-Error: " .$this->db->getLastError();
				return false;
			}
			return false; 
		}	
	}



	public function delete($id) 
	{
		// I should check here if the user MAY delete that model at all!?

		// call the hook beforeDelete if existing:
		if(method_exists($this, 'beforeDelete')) {
			$data = $this->beforeDelete($id);
			// WHAT Shall the hook provide/do? 
			// there's no data to be transformed. Maybe only implement a delete-blocker?
		}

		$this->db->where ($this->dbIdField, $id);
		$this->db->delete($this->dbTable);
		return $this->db->count;
	}


	/*
	*	method sort
	*
	*/
	public function sort($reference, $id, $direction=null, $currentSort=null) 
	{
		global $err;
		switch ($direction) {
			case 'up':
				$currentSort = intval($currentSort);
				$targetSort = $currentSort-1;
				if($targetSort<0) {
					$err->add($err::MODEL_SORT_OOR);
					return false;
				}
				// first reset the one that the new sort is replacing
				$sql = "Update ".$this->dbTable." set sort=? where sort=? AND ".$this->sortBy."=?";
				$params = Array($currentSort, $targetSort, $reference);
				$this->db->rawQuery($sql, $params);
				// then update the actual record
				$sql = "Update ".$this->dbTable." set sort=? where id=?";
				$params = Array($targetSort, $id);
				$this->db->rawQuery($sql, $params);
				if($this->db->getLastError()) {
					$err->add($err::DB_ERROR);
					return false;
				} else {
					// returnung all items in this reference:
					$this->db->where($this->sortBy, $reference);
					$items = $this->db->get($this->dbTable);
					foreach ($items as $i => $item) {
						$items[$i] = $this->_unsetHiddenProperties($item);
					}
					return $items;
				}

				break;
			case 'down':
				$currentSort = intval($currentSort);
				$targetSort = $currentSort+1;

				// check if we CAN sort back, hence if the item is not the last one already
				$this->db->where($this->sortBy, $reference);
				$maxSort = $this->db->getValue ($this->dbTable, "max(sort)");
				// echo "maxSort: ".$maxSort."\n";
				// echo "currentSort: " .$currentSort."\n";
				if($maxSort===$currentSort) {
				 	$err->add($err::MODEL_SORT_OOR);
					return false;
				}
				// first reset the one that the new sort is replacing
				$sql = "Update ".$this->dbTable." set sort=? where sort=? AND ".$this->sortBy."=?";
				$params = Array($currentSort, $targetSort, $reference);
				$this->db->rawQuery($sql, $params);
				// then update the actual record
				$sql = "Update ".$this->dbTable." set sort=? where id=?";
				$params = Array($targetSort, $id);
				$this->db->rawQuery($sql, $params);
				if($this->db->getLastError()) {
					$err->add(20);
					return false;
				} else {
					// returning all items in this reference:
					$this->db->where($this->sortBy, $reference);
					$items = $this->db->get($this->dbTable);
					foreach ($items as $i => $item) {
						$items[$i] = $this->_unsetHiddenProperties($item);
					}
					return $items;
				}
				break;
			default:

		}
	
	}

	public function getDbTable() {
		return $this->dbTable;
	}


	/*
	*	method addUser
	* DEPRECATED use addMany2Many instead!!!
	*
	*/
	public function addUser($type, $data) 
	{
		echo "Method addUser in Class Model is DEPRECATED. Use addMany2Many instead.";
		$data = (Object) $data;
		//var_dump($data);
		$id = $data->user . '_' . $data->{$type};
		$data->id = $id;
		// check if the user is already Member (even with lower rights)
		$this->db->where('id', $id);
		$result = $this->db->getOne('user2'.$type);
		if($result) {
			if($result['rights']<$data->rights) {	// user had lower rights then
				$dataArray = Array(
					"rights"=>$data->rights,
					"modBy"=>$data->modBy
					);
				// update in database
				$this->db->where('id', $id);
				$this->db->update('user2'.$type, $dataArray);
				if($this->db->count) { return $id; }
				else { return false; }
			} else {
				echo "already Member";
				// user was member already
				return false;
			}
		}
		// user was not connected to this workgroup/production/audition yet, so insert him:
		$data->memberSince = $this->db->now();
		$dataArray = (Array) $data;
		$success = $this->db->insert('user2'.$type, $dataArray);
		// echo $success;
		return ($success) ?  $id : false;

	}



	//
	// SPECIALS FOR GETTING DATA
	//
	private function _addHasMany($item, $id) {
		if(!($this->hasManyFields) || !is_array($this->hasManyFields)) {
			return $item;
		}
		for ($i=0; $i < count($this->hasManyFields); $i++) { 
			$hmf = $this->hasManyFields[$i];
			if ($this->db->tableExists($hmf['dbTable'])) {
				$this->db->where($hmf['dbSourceFieldName'], $id);
				// if(isset($hmf['orderBy'])) {
				// 	$this->db->orderBy($hmf['orderBy']['field'], $hmf['orderBy']['direction']);
				// }
				$one2many = $this->db->get($hmf['dbTable'], null, Array('id',$hmf['dbTargetFieldName']));
				// echo "one2many: \n";
				// $this->db->getLastQuery();
				#var_dump($one2many);
				$refIds = Array();
				$store = Array();
				foreach ($one2many as $key => $value) {
					$refIds[] = $value['id'];
					$store[] = $value[$hmf['saveToStoreField']];
				}
				$item[$hmf['name']] = $refIds;
				$this->sideLoadTargetsStore[$hmf['saveToStoreName']] = $store;
			}
		}
		return $item;
	}

	private function _addHasManyForSideload($item, $id, $hasManyFields=NULL) {
		if(!($hasManyFields) || !is_array($hasManyFields)) {
			return $item;
		}
		for ($i=0; $i < count($hasManyFields); $i++) { 
			$hmf = $hasManyFields[$i];
			
			if ($this->db->tableExists ($hmf['dbTable'])) {
				$this->db->where($hmf['dbSourceFieldName'], $id);
				$one2many = $this->db->get($hmf['dbTable'], null, Array('id',$hmf['dbTargetFieldName']));
				#echo "\n".$this->db->getLastQuery()."\n\n";
				$refIds = Array();
				foreach ($one2many as $key => $value) {
					$refIds[] = $value['id'];
				}
				$item[$hmf['name']] = $refIds;
			}
		}
		return $item;
	}

	private function _addSideloads($item) {
		if(count($this->sideloadItems)) {
			$this->sideload = new \stdClass();
		}
		for ($i=0; $i < count($this->sideloadItems); $i++) { 
			$sli = $this->sideloadItems[$i];

			if (isset($item[$sli['reference']]) && count($item[$sli['reference']])) {	// add only if we have values linked
				$this->db->where('id', $item[$sli['reference']] , 'IN');
				$result = $this->db->get($sli['dbTable']);
				for ($r=0; $r < count($result); $r++) { 
					$result[$r] = $this->_unsetHiddenProperties($result[$r]);
				}
				$this->sideload->{$sli['name']} = $result;
			}

			if(isset($this->sideLoadTargetsStore[$sli['name']]) && count($this->sideLoadTargetsStore[$sli['name']])) {
				$this->db->where('id', $this->sideLoadTargetsStore[$sli['reference']] , 'IN');	
				$result = $this->db->get($sli['dbTable']);

				for ($r=0; $r < count($result); $r++) { 
					$result[$r] = $this->_unsetHiddenProperties($result[$r]);
					// add hasMany from child Class
					if(isset($sli['class'])) {
						#echo 'require_once class: '.$sli['class']."\n";
						require_once($sli['class'].'.php');
						$className = __NAMESPACE__ . "\\" . $sli['class'];
						$class = new $className();
						$result[$r] = $this->_addHasManyForSideload($result[$r], $result[$r]['id'], $class->hasManyFields);
					}
				}
				$this->sideload->{$sli['name']} = $result;
			}
		}
	}

	// HELPERS
	private function _unsetHiddenProperties($item) {
		for ($i=0; $i < count($this->hiddenProperties); $i++) { 
			unset($item[$this->hiddenProperties[$i]]);
		}
		return $item;
	}


	/*
	*	method _makeAssociativeFieldsArray(String $table, Array $fields) 
	*
	*	transferes the given array fields from 'name' to 'tablename.name name'
	*	this is needed in method 'getAll()', to get the field names ready for a sql-join,
	*	which is again needed to include hasManyFields 
	*/
	private function _makeAssociativeFieldsArray($table, $fields) {
		$x = array();
		foreach ($fields as $field) {
			$x[] = $table.'.'.$field[0]. ' '. $field[0];
		}
		return $x;
	}



}

// SUB Classes
class ModelRestriction {
	public $type;
	public $referenceField;
	public $id;

	public function __construct($type, $referenceField, $id) {
		$this->type = $type;
		$this->referenceField = $referenceField;
		$this->id = $id;
	}
}