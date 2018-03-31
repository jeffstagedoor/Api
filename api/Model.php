<?php
/**
*	Class Model
*	this is the basic Class for all models
*	includes getters and setters as far as they can be generalized
*	
*	@author Jeff Frohner
*	@copyright Copyright (c) 2015-2016
*	@license   private
*	@version   1.8.1
*
**/
namespace Jeff\Api\Models;
use Jeff\Api as Api;



Class Model {

	// a modelName MUST NOT include a '2', because otherwise it'll be treated as a relational-model
	protected $modelName = null;
	protected $modelNamePlural = null;
	protected $motherModel = null; // this will be needed to make a bubbleUp method, 
								// to determine all the mother-models of a grand-child model such as a track, 
								// that belongsTo an artistgroup, an production, a workgroup
								// will be needed to figure out if a user may edit/delete an item or not
	
	protected $dbDefinition = array( 	
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
	public $modifiedByField = 'modBy';
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
	public $cols = null;
	public $lastInsertedID = -1;

	public $sideload = null;
	public $sideLoadTargetsSave = Array();

	// maskFields
	// these fields will be masked with *** according to it's properties
	protected $maskFields = Array(
		// 'email' => Jeff\Api\DataMasker::MASK_TYPE_EMAIL,
		// 'tel' => Jeff\Api\DataMasker::MASK_TYPE_TEL,
		);

	/** 
	* what data (=db-fields) to send when querying a search 
	*/
	protected $searchSendCols = Array('id');	

	// hiddenProperties
	// what properties will be unset before sending the payload
	// allways remove password, authToken, auth, ...
	protected $hiddenProperties = Array('password', 'authToken', 'auth', 'refreshToken');

	// validateFields
	// what fields shall be validated before inserting/updating
	protected $validateFields = Array(
		// Array('field' => 'email', 'valtype' => Jeff\Api\Validate::VAL_TYPE_EMAIL, 'arg' => null),
		// Array('field' => 'description', 'valtype' => Jeff\Api\Validate::VAL_TYPE_LENGTH_MAX, 'arg' => 300),
		// Array('field' => 'description', 'valtype' => Jeff\Api\Validate::VAL_TYPE_LENGTH_MIN, 'arg' => 300),
		);

	// list of db fields that shall not be updated in a normal api-update. Those have to be set via task (or special account-api calls such as 'signin')
	protected $doNotUpdateFields = [];

	// hasMany-Fields

	protected $hasMany = Array (
		/*
			"account2workgroup"=> Array(
				"db"=>array(
						array('id', 'varchar', '20', false),
						array('account', 'int','11', false),
						array('workgroup', 'int', '11', false),
						array('rights', 'tinyint', '4', false),
						array('invitedBy', 'int', '11', false),
						array('invitedDate', 'timestamp', null, false, 'CURRENT_TIMESTAMP', 'ON UPDATE CURRENT_TIMESTAMP'),
						array('memberSince', 'timestamp', null, true, 'NULL'),
						array('isRequest', 'tinyint', '1', true),
						array('requestDate', 'timestamp', null, true, 'NULL'),
						array('requestAcceptedBy', 'int', '11', true),
						array('requestAcceptedDate', 'timestamp', null, true, 'NULL')
				),
				"primaryKey"=>"workgroup"
			)
		*/
		);

	// what Items to send as sideload when doing a simple getOneById()
	protected $sideloadItems = Array( 
			// Array("name"=>'user2workgroups',"dbTable"=>'user2workgroup', "reference"=>'user2workgroups'),
			// Array("name"=>'workgroups', "dbTable"=>'workgroups', "reference"=>'workgroups'),
			// Array("name"=>'user2productions', "dbTable"=>'user2production', "reference"=>'user2productions'),
			// Array("name"=>'productions', "dbTable"=>'productions', "reference"=>'productions'),
		);

	/**
	*	A list of methods that can be called via special api call www.example.com/api/modelname/specialMethod
	*	Unless the method is listed here it won't be called (for security)
	*
	*/
	public $specialMethods = Array();

	// CONSTANTS
	const SEARCH_TYPE_STRICT = "strict";
	const SEARCH_TYPE_SEMISTRICT = "semistrict";
	const SEARCH_TYPE_LOOSE = "loose";
	const SEARCH_TYPE_VERYLOOSE = "veryloose";
	const SEARCH_MIN_LENGTH = 4;


	// CONSTRUCTOR
	// always pass the fitting db-object to contructor
	// possible Hook: initializeHook()
	public function __construct($db, $ENV, $errorHandler, $account, $request=NULL) 
	{
		$this->db = $db;
		$this->ENV = $ENV;
		$this->errorHandler = $errorHandler;
		$this->cols = $this->_makeAssociativeFieldsArray($this->dbTable, $this->dbDefinition);
		$this->account = $account;
		$this->request = $request;

		if(method_exists($this, "initializeHook")) {
			$this->initializeHook();
		}
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
	*/
	public function getOneById($id) 
	{
		// check if the child-model has the beforeGetAll-Hook implemented
		// Standard Example for such a hook: 
		// 
		// 	 function beforeGetOne($id) {
		// 		global $Account;
		// 		$restriction = new ModelRestriction('REF_ID', 'account', $Account->id);
		// 		$restrictions = Array();
		// 		$restrictions[] = $restriction;
		// 		return $restrictions;
		//   }
		//
		// checking for quthorization first:


		if( isset($this->request->type) && $this->request->type!=Api\Api::REQUEST_TYPE_SPECIAL) {
			if(file_exists($this->ENV->dirs->appRoot."AuthorizationConfig.php")) {
				include_once($this->ENV->dirs->appRoot."AuthorizationConfig.php");
				$Authorizor = new \Jeff\Api\Authorizor\Authorizor($Settings, $this->account, $this->db);
				// check if we have settings for that model
				$isAuthorized = $Authorizor->authorize($this->modelName, $this->modelNamePlural, $id, 'mayView');
				if(!$isAuthorized) {
					$this->errorHandler->throwOne(Array('Not allowed', 'You are not allowed to access this recource', 200, \Jeff\Api\ErrorHandler::CRITICAL_LOG,false));
					exit;
				}
			// } else {
			// 	$isAuthorized = true;
			}
		}

		$this->db->where($this->dbIdField,$id);
		$item = $this->db->getOne($this->dbTable);
		if($item) {
			$item = $this->_unsetHiddenProperties($item);
			$item = $this->_addHasMany($item, $id); 	// add hasMany-Relationships, defined in Child-Model Class as $hasMany
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
		// check if the child-model has the beforeGetAll-Hook implemented
		// Standard Example for such a hook: 
		// 
		// 	 function beforeGetAll() {
		// 		global $Account;
		// 		$restriction = new ModelRestriction('REF_IS', 'account', $Account->id);
		// 		$restrictions = Array();
		// 		$restrictions[] = $restriction;
		// 		return $restrictions;
		//   }
		//

		if(method_exists($this, 'beforeGetAll')) {

			// if so, get the restrictions
			$restrictions = $this->beforeGetAll();
			// var_dump($restrictions);
			// and apply them, depending on their type
			foreach ($restrictions as $key => $restriction) {
				if(!is_array($restriction->data) || count($restriction->data)==0) {
					$restriction->data[] = -1;
				}

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


		$items = $this->_getResultFromDb();
		// echo $this->db->getLastQuery()."\n";
		$items = $this->_unsetHiddenPropertiesMultiple($items);
		$items = $this->_addHasManyMultiple($items);
		$this->_addSideloadsMultiple($items); 			// add sideloads, defined in Child-Model Class as $sideloadItems
		return $items;
	}

	/**
	*	method getCoalesce($coalesceIds)
	*   if we get an array of ids, as they arrive when doing an coalesceFindRecord call (in ember RestAdapter 'coalesceFindRequests: true')
	*   we return only the corresponding items 
	*	
	*	@param coalesceIds (Array)
	**/
	public function getCoalesce(array $coalesceIds=null) {
		if($coalesceIds) {
			$this->db->where($this->dbTable.'.id', $coalesceIds,'IN');
			$items = $this->_getResultFromDb();
			$items = $this->_addHasManyMultiple($items);
			$items = $this->_unsetHiddenPropertiesMultiple($items);
			$this->_addSideloadsMultiple($items);
			return $items;
		} else {
			$this->errorHandler->throwOne(ErrorHadler::API_INVALID_GET_REQUEST);
			return false;
		}
	}





	/**
	*	MANY TO MANY Relationships
	*
	* this depends on following conventions:
	* db-tables/models that represent a manyToMany Relationship have this name/structure:
	* - dbTable= 'needles2haystacks'; e.g. 'users2workgroups' (both plural)
	* - id-field = needle_id + '_' + haystack_id (eg 2_15)
	*
	*
	*
	*
	**/
	public function getMany2Many(string $id=null, string $modelLeftNamePlural, $by='id', $filters=null) {
		$tableName = $modelLeftNamePlural.'2'.$this->modelNamePlural;
		if(is_array($id)) {
			// then it's likely a coalesceFindrecord call,
			// so we should return all items where id in that array
			$this->db->where($by, $id, 'IN');
		} elseif ($id!=null) {
			// normal singular request or referential request 'by'
			$this->db->where($by, $id);
		}
		if(!is_null($filters) && is_array($filters) && count($filters)>0) {
			// we have a filter applied
			foreach ($filters as $filter) {
				$this->db->where($tableName.'.'.$filter['key'], $filter['value'], $filter['comp']);
			}
		}

		$many2many = $this->db->get($tableName);
		#echo $this->db->getLastQuery();
		if(count($many2many)===1 && !is_null($id)) {
			// one item found (and requested)
			return $many2many[0];
		} elseif (count($many2many)>0) {
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
	public function updateMany2Many($modelLeft, $id, $data) {
		// call the hook beforeUpdateMany2Many if existing:
		if(method_exists($this, 'beforeUpdateMany2Many')) {
			$data = $this->beforeUpdateMany2Many($modelLeft, $id, $data);
		}

		$this->db->where('id', $id);
		$success = $this->db->update($modelLeft->modelNamePlural.'2'.$this->modelNamePlural, $data);
		#echo $this->db->getLastQuery();
		#echo $this->db->getLastError();
		return ($success) ?  $id : false;
	}


	// possible HOOK:
	// public function beforedeleteMany2Many($modelLeft, $id) {
	// 	return $true;
	// }
	public function deleteMany2Many($modelLeft, $id) {
		// call the hook beforeDeleteMany2Many if existing:
		if(method_exists($this, 'beforeUpdateMany2Many')) {
			$bool = $this->beforeUpdateMany2Many($modelLeft, $id);
		} else {
			$bool = true;
		}
		if($bool) {
			$this->db->where('id', $id);
			$success = $this->db->delete($modelLeft->modelNamePlural.'2'.$this->modelNamePlural);
			return ($success) ? $id : false;
		}
		return false;
	}




	public function getLastInsertedId() {
		return $this->lastInsertedID;
	}



	/*
	*	method search()
	*	
	*/
	public function search($data) {
		if(isset($data->condition) && $data->condition==='or') {
			$or = true;
			unset($data->condition);
		} else {
			$or = false;
			unset($data->condition);
		}
		$cnt=0;

		$searchType = isset($data->searchType) ? $data->searchType : self::SEARCH_TYPE_STRICT;
		unset($data->searchType);

		switch ($searchType) {
			case self::SEARCH_TYPE_STRICT:
				$operator = "=";
				break;
			case self::SEARCH_TYPE_SEMISTRICT:
			case self::SEARCH_TYPE_LOOSE:
			case self::SEARCH_TYPE_VERYLOOSE:
				$operator = "LIKE";
				break;
			default:
				$operator = "=";
		}
		#echo "operator: ".$operator."\n";

		if(isset($data->restrictions)) {
			$restrictions = $data->restrictions;
			unset($data->restrictions);
			foreach ($restrictions as $key => $value) {
				if(!in_array($key, $this->searchSendCols)) {
					$this->errorHandler->throwOne(Array("API-Error", "Search: A Model-Key was added to search-restrictions, but is not included in searchSendCols of the Model.\nModel: ".$this->modelName."\nthe key in question: ".$key, 500, Api\ErrorHandler::CRITICAL_EMAIL, true));
					$this->errorHandler->throwOne(Array("API-Error", "The search could not be fulfilled", 500, Api\ErrorHandler::CRITICAL_EMAIL, false));
					exit;
				}
				$this->db->having($key, $value);
			}
		}

		foreach ($data as $key => $value) {
			if(!is_string($value)) {
				$this->errorHandler->throwOne(Array("API-Error", "No valid search data found. Please consult readme to find needed format.", 500, Api\ErrorHandler::CRITICAL_EMAIL, false));
				exit;
			}
			// for security, I first replace any placeholder with a questionmark
			// if not, a user could search for "%%%%" and would get all datasets...we dont want that
			$value=preg_replace('/[%*]/', "", $value);
			// still, if we have less then const::SEARCH_MIN_LENGTH letters without the special characters,
			// abort transforming to loose.... (otherwise a user could search for '------') and still get all datasets
			$test = preg_replace('/[ \-_\´\`\']/', "%", $value);
			if(strlen($test) < $this::SEARCH_MIN_LENGTH) {
				$searchType=self::SEARCH_TYPE_STRICT;
			}
			if($searchType === self::SEARCH_TYPE_LOOSE) {
				$value=preg_replace('/[ \-_\´\`\']/', "%", $value);
			}
			if($searchType === self::SEARCH_TYPE_VERYLOOSE) {
				echo $value."\n";
				$value=preg_replace('/[ \-_\´\`\']/', "%", $value);
				echo $value."\n";
				$value='%'.$value.'%';
			}
			if(!in_array($this->dbTable.".".$key." ".$key, $this->cols)) {
				$this->errorHandler->throwOne(Array("API-Error", "Searching for field '$key', which doesn't exist in model '$this->modelName'. Please consult readme to find needed format.", 500));
				exit;
			}
			if($cnt && $or) {
				$this->db->orWhere($key, $value, $operator);
			} else {
				$this->db->where($key, $value, $operator);
			}
			$cnt++;
		}
		if($cnt) { // if I have minimum one search item, give a result
			$cols = (count($this->searchSendCols)>1) ? $this->searchSendCols : $this->cols;
			$result = $this->db->get($this->dbTable, NULL, $cols);
			// echo $this->db->getLastQuery()."\n";
			// Mask properties/fields that where defined to be masked in the Model ('maskFields') and remove the properties, that shall be removed
			for ($i=0; $i <count($result) ; $i++) {
				foreach ($this->maskFields as $field => $type) {
					if(isset($result[$i][$field])) {
						$result[$i][$field] = \Jeff\Api\DataMasker::mask($result[$i][$field], $type);
					}
				}
				foreach ($this->hiddenProperties as $key => $field) {
					unset($result[$i][$field]);
				}
					
			}
		} else { // else generate an error
			$result = null;
			$this->errorHandler->throwOne(Array("API Error", "search: no search value found: Model ".$this->modelName, 500, Api\ErrorHandler::CRITICAL_EMAIL, true));
			$this->errorHandler->throwOne(Array("API Error", "no value to search for was found", 500, Api\ErrorHandler::CRITICAL_LOG, false));
			exit;
		}
		return $result;
	}

	/*
	*	method count()
	*	
	*/
	public function count($delimiters=null) {
		if(is_object($delimiters)) {

			foreach ($delimiters as $key => $value) {
				if(!in_array($this->dbTable.".".$key." ".$key, $this->cols)) {
					$this->errorHandler->throwOne(Array("API-Error", "Counting with delimiter '$key', which doesn't exist in model '$this->modelName'. Please consult readme to find needed format.", 400));
					exit;
				}
				$this->db->where($key, $value);
			}
		} 
		// elseif(is_array($delimiters)) {
		// 	for ($i=0; $i < count($delimiters); $i++) { 
		// 		if(!in_array($this->dbTable.".".$delimiter[$i]->key." ".$delimiter[$i]->key, $this->cols)) {
		// 			$this->errorHandler->throwOne(Array("API-Error", "Counting with delimiter '$key', which doesn't exist in model '$this->modelName'. Please consult readme to find needed format.", 400));
		// 			exit;
		// 		}
		// 		$this->db->where($delimiters[$i]->, $delimiters[$i]['value']);
		// 	}
		// }
		$count = $this->db->getValue($this->dbTable, "count(*)");
		return $count;
	}


	//
	// SETTERS
	//

	/**
	*	method add()
	*	possible Hooks: beforeAdd($data), afterAdd($data, $id)
	* 	@param [array] $data	
	*	@return [int] the new id of the item, false if an error occurred
	*/
	public function add($data) {

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
		// unsetting hasMany that might come from Ember via POST:
		foreach ($this->hasMany as $name => $hasMany) {
			unset($data[$name]);
		}
		// check if we have all data we need:
		$required = Api\ApiHelper::getRequiredFields($this->dbDefinition);
		$missingFields = Api\ApiHelper::checkRequiredFieldsSet($required, $data);
		if(count($missingFields)===0) {
			// the actual insert into database:
			$id = $this->db->insert($this->dbTable, $data);
			if($id) {
				if(method_exists($this, 'afterAdd')) {
					$data = $this->afterAdd($data, $id);
				}
				$this->lastInsertedID = $id;
				return $id;
			} else {
				$e = Array("DB-Error", "Could not insert item because: ".$this->db->getLastError()."\nin Model ".__FUNCTION__." ".__LINE__, 500, true, Api\ErrorHandler::CRITICAL_EMAIL);
				$this->errorHandler->add($e);
				$e = Array("DB-Error", "Could not insert item", 500, Api\ErrorHandler::CRITICAL_LOG, false);
				$this->errorHandler->add($e);
				$this->errorHandler->sendErrors();
				$this->errorHandler->sendApiErrors();
				exit;
			} 
		} else {
			$e = Array("API-Error", "Not all required fields were sent. I missed: ".implode(",", $missingFields), 400, false, Api\ErrorHandler::CRITICAL_EMAIL);
			$this->errorHandler->add(new Api\Error($e));
			$this->errorHandler->add(Api\ErrorHandler::API_INVALID_POST_REQUEST);
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
	public function addMany2Many($modelLeft, $data) {
		$id = $data[$modelLeft->modelName] . '_' . $data[$this->modelName];
		$data['id'] = $id;
		$relationTableName = $modelLeft->modelNamePlural.'2'.$this->modelNamePlural;
		
		// check if the artist is already Member (even with other role)
		$this->db->where('id', $id);
		try {
			$result = $this->db->getOne($relationTableName);
		} catch (Exception $e) {
			$this->errorHandler->add(20);
			$this->errorHandler->add(Array("DB-Error", "could not get relationTable '".$relationTableName."'", true, Api\ErrorHandler::CRITICAL_EMAIL, $e));
			$this->errorHandler->sendAllErrorsAndExit();
		}
			
		if($result) {
			if(isset($data[$this->many2manyParam]) && $result[$this->many2manyParam]!=$data[$this->many2manyParam]) {	// artist had other role then
				$udData = Array(
					$this->many2manyParam => $data[$this->many2manyParam],
					"modBy" => $data['modBy']
					);

				// call the hook beforeUpdateMany2Many if existing:
				if(method_exists($this, 'beforeUpdateMany2Many')) {
					$udData = $this->beforeUpdateMany2Many($id, $modelLeft, $udData);
				}

				// update in database
				$this->db->where('id', $id);
				$this->db->update($relationTableName, $udData);
				if($this->db->count) { return $id; }
				else { return false; }
			} else {
				// artist was connected with same role already
				$this->errorHandler->add(array('API-Error','Relation already existst',400,$this->errorHandler::CRITICAL_LOG,false));
				$this->errorHandler->sendAllErrorsAndExit();
			}
		}
		// call the hook beforeAddMany2Many if existing:
		if(method_exists($this, 'beforeAddMany2Many')) {
			$data = $this->beforeAddMany2Many($modelLeft, $data);
		}
		// artist/user was not connected to this production/track/whatever yet, so insert him:
		if(isset($data['memberSince']) || (isset($data['memberSince']) && is_null($data['memberSince']))) {
			$data['memberSince'] = $this->db->now();
		} 
		$success = $this->db->insert($relationTableName, $data);
		if($success) {
			return $id;
		} else {
			$this->errorHandler->add(20);
			$this->errorHandler->add(Array("DB-Error", "could not add relation '".$relationTableName."'. DB-Error: ".$this->db->getLastError(), true, Api\ErrorHandler::CRITICAL_EMAIL));
			$this->errorHandler->sendErrors();
			$this->errorHandler->sendApiErrors();
			return false;
		}
	}

	/*
	* 	method addMultiple()
	*	needs function addMultipleHook implemented in childModel
	*
	*/
	public function addMultiple($data, $multipleParams) {
		// call the hook beforeAdd if existing:
		if(method_exists($this, 'beforeAddMultiple')) {
			$data = $this->beforeAddMultiple($data, $multipleParams);
		}

		// unsetting hasMany-Fields that might come from Ember via POST:
		foreach ($this->hasMany as $hmfName=>$hmf) {
			unset($data[$hmfName]);
		}


		if(method_exists($this, 'addMultipleHook')) {
			$insertedIds = $this->addMultipleHook($data, $multipleParams);
		} else {
			$this->errorHandler->throwOne(\Jeff\Api\ErrorHandler::API_INVALID_POST_REQUEST);
		}

		// now return all newly inserted events/items:
		if(count($insertedIds)) {
			$this->db->where($this->dbTable.'.'.$this->dbIdField, $insertedIds, "IN");
			$result = $this->_getResultFromDb();
			$items = $this->_addHasManyMultiple($result);
		} else {
			$items = array();
		}
		return $items;
	}


	public function update($id, $data) {
		

		// call the hook beforeUpdate if existing:
		if(method_exists($this, 'beforeUpdate')) {
			$data = $this->beforeUpdate($id, $data);
		}

		// I should check here if the user MAY update that model at all!?
		if(is_object($data)) {
			$data = (Array) $data;
		}
		// unsetting hasMany-Fields that might come from Ember via POST:
		foreach ($this->hasMany as $hasManyName => $hasManyItem) {
			unset($data[$hasManyName]);
		}

		if(isset($this->doNotUpdateFields)) {
			foreach ($this->doNotUpdateFields as $fieldName) {
				unset($data[$fieldName]);
			}
		}


		$this->db->where($this->dbIdField, $id);
		// print_r($data);
		try {
			if ($this->db->update($this->dbTable, $data)) {
				#echo $this->db->getLastQuery();
				$return = new \stdClass();
				$return->count = $this->db->count;
				$return->id = $id;
				return $return; // $db->count.' records were updated';
			}
			
		} 
		catch (Exception $e) {
			$this->errorHandler->add(array("Model Update Error", "Standard update failed", Api\ErrorHandler::CRITICAL_EMAIL, true, $e));
			if($this->db->getLastError()>'') { 
				$this->errorHandler->add(new Api\Error($err::DB_UPDATE, $this->db->getLastError()));
				return false;
			}
			return false; 
		}	
	}



	public function delete($id) {
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
	public function sort($reference, $id, $direction=null, $currentSort=null) {
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
					$items = $this->_unsetHiddenPropertiesMultiple($items);
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
				 	$this->errorHandler->add(Api\ErrorHandler::MODEL_SORT_OOR);
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
					$this->errorHandler->add(20);
					return false;
				} else {
					// returning all items in this reference:
					$this->db->where($this->sortBy, $reference);
					$items = $this->db->get($this->dbTable);
					$items = $this->_unsetHiddenPropertiesMultiple($items);
					return $items;
				}
				break;
			default:

		}
	
	}

	public function getDbTable() {
		return $this->dbTable;
	}



	//
	// SPECIALS/INTERNALS FOR GETTING DATA
	//

	/**
	*	method _addHasMany(array $items, int $id, array $hasMany)
	*
	*	@param 	(Array)	items: the main result the hasMany Items shall be added to
	*			(int) 	id: the actual id of the main (parent) model
	*			(Array)	hasMany: only needed when called from _addSideload(), because then we are not inside this model anymore.
	*	@return (Array) items
	*
	**/
	private function _addHasMany($item, $id, $hasMany=NULL) {
		// if I'm coming from addSideload, I'll also get hasMany passed in, 
		// if not, take that from the current model, as we are there already
		if(is_null($hasMany)) {	
			$hasMany = $this->hasMany;
		}
		// if there are still no hasMany to get, just return what you have gotten....
		if(!($hasMany) || !is_array($hasMany)) {
			return $item;
		}
		foreach ($hasMany as $hmName => $hasManyItem) { 
			// echo "hasManyName: ".$hmName."\n";
			if ($this->db->tableExists($hmName)) {
				// echo "tableExists\n";
				$this->db->where($hasManyItem['sourceField'], $id);
				$one2many = $this->db->get($hmName, null, Array('id',$hasManyItem['sourceField']));
				// echo $this->db->getLastQuery()."\n\n";
				$refIds = Array();
				foreach ($one2many as $key => $value) {
					$refIds[] = $value['id'];
				}
				$item[$hmName] = $refIds;
			}
		}
		return $item;
	}

	// private function _addHasManyForSideload($item, $id, $hasMany) {
	// 	if(!($hasMany) || !is_array($hasMany)) {
	// 		return $item;
	// 	}
	// 	foreach ($hasMany as $hmName => $hasManyItem) {

	// 		if ($this->db->tableExists($hmName)) {
	// 			$this->db->where($hasManyItem['sourceField'], $id);
	// 			$one2many = $this->db->get($hmName, null, Array('id',$hasManyItem['sourceField']));
	// 			$refIds = Array();
	// 			foreach ($one2many as $key => $value) {
	// 				$refIds[] = $value['id'];
	// 			}
	// 			$item[$hmName] = $refIds;
	// 		}
	// 	}
	// 	return $item;
	// }

	/**
	*	method _addHasManyMultiple(array $items)
	*
	*	@param (Array) items: the main result the hasMany Items shall be added to
	*	@return (Array) items
	*
	**/
	private function _addHasManyMultiple($items) {
		if(!($this->hasMany) || !is_array($this->hasMany)) {
			// if there are no hasMany to get, just return what you have gotten....
			return $items;
		}
		
		// walk all the items, save them ids in an array, do select with 'IN', walk the items again to add the hasMany
		if(!count($items)) { // no items -> so we don't need to walk them, just return the empty array of items
			return $items;
		} else {
			$ids = array();
			foreach ($items as $item) {
				$ids[]=$item[$this->dbIdField];
			}
		}

		// now we have all the ids of the main items
		foreach ($this->hasMany as $hmName => $hasMany) {
			$this->db->where($this->modelName, $ids, 'IN');
			$hm = $this->db->get($hmName, null, array('id', $this->modelName));

			foreach ($items as &$item) {
				if(!isset($item[$hmName])) {
					$item[$hmName] = array();
				}
				foreach ($hm as $hasManyItem) {
					if($item['id']===$hasManyItem[$this->modelName]) {
						array_push($item[$hmName], $hasManyItem['id']);
					}
				}
			}
		}
		return $items;
	}


	private function _addSideloads($item) {
		if(count($this->sideloadItems)) {
			$this->sideload = new \stdClass();
		}
		for ($i=0; $i < count($this->sideloadItems); $i++) { 
			$sideloadItem = $this->sideloadItems[$i];
			if (isset($item[$sideloadItem['name']]) && count($item[$sideloadItem['name']])) {	// add only if we have values linked
				$this->db->where('id', $item[$sideloadItem['name']] , 'IN');
				$result = $this->db->get($sideloadItem['name']);

				for ($r=0; $r < count($result); $r++) { 
					$result[$r] = $this->_unsetHiddenProperties($result[$r]);
					
					// hier muss ich die hasMany hinzufügen:
					if(count(explode("2",$sideloadItem['name']))===2) { 
						//skip reference-requests, that would be something like accounts2workgroups
						continue;
					}
					if(!isset($models[$sideloadItem['name']])) {
						// only load the model once...

						$sideloadModelFileName = $sideloadItem['name'].".php";
						
						include_once($this->ENV->dirs->models.DIRECTORY_SEPARATOR.$sideloadModelFileName);
						$className = $sideloadItem['name'];
						// echo "className: $className\n";
						$classNameNamespaced = "\\Jeff\\Api\\Models\\" . ucfirst($className);
						$models[$sideloadItem['name']] = new $classNameNamespaced($this->db, $this->ENV, $this->errorHandler, $this->account);
					}
					$result[$r] = $this->_addHasMany($result[$r], $result[$r]['id'], $models[$sideloadItem['name']]->hasMany);

				}
				$this->sideload->{$sideloadItem['name']} = $result;
			}


			// wozu brauch ich das??? ANSWER: to addhasManyForSideloads
			// das funktioniert nämlich nicht, seit Umbau von hasMany-declaration
			// if(isset($this->sideLoadTargetsStore[$sli['name']]) && count($this->sideLoadTargetsStore[$sli['name']])) {
			// 	echo "bin in Model _addSideloads - sideLoadTargetsStore !?";
			// 	$this->db->where('id', $this->sideLoadTargetsStore[$sli['name']] , 'IN');	
			// 	$result = $this->db->get($sli['name']);
			// 	echo $this->db->getLastQuery();
			// 	var_dump($result);
			// 	for ($r=0; $r < count($result); $r++) { 
			// 		$result[$r] = $this->_unsetHiddenProperties($result[$r]);
			// 		// add hasMany from child Class
			// 		if(isset($sli['class'])) {
			// 			#echo 'require_once class: '.$sli['class']."\n";
			// 			require_once($sli['class'].'.php');
			// 			$className = __NAMESPACE__ . "\\" . $sli['class'];
			// 			$class = new $className();
			// 			$result[$r] = $this->_addHasManyForSideload($result[$r], $result[$r]['id'], $class->hasMany);
			// 		}
			// 	}
			// 	$this->sideload->{$sli['name']} = $result;
			// }
		}
	}

	private function _addSideloadsMultiple($items) {
		if(count($this->sideloadItems)) {
			$this->sideload = new \stdClass();
		}

		// var_dump($items);
		$tmpSideloadIds = new \stdClass();
		foreach ($items as $item) {
			// search through the sideloadItems in the item
			for ($i=0; $i < count($this->sideloadItems); $i++) { 
				$sideloadItem = $this->sideloadItems[$i];
				// init the tmp-array
				if(!isset($tmpSideloadIds->{$sideloadItem['name']})) {
					$tmpSideloadIds->{$sideloadItem['name']} = [];
				}
				$tmpSideloadIds->{$sideloadItem['name']} = array_merge($tmpSideloadIds->{$sideloadItem['name']}, $item[$sideloadItem['name']]);
			}
		}
		foreach ($tmpSideloadIds as $key => $ids) {
			if(is_array($ids) && count($ids)) {
				$this->db->where('id', $ids , 'IN');
				$result = $this->db->get($key);
				$this->sideload->{$key} = $result;
			}
		}
	}

	// HELPERS
	/*
	* private function _getResultFromDb()
	*/
	private function _getResultFromDb() {
		global $err;

		$db2 = new \MysqliDb($this->ENV->database);
		// $tableResult = $this->db->rawQuery("SHOW FULL TABLES LIKE '".$this->dbTable."'");
		$tableResult = $db2->rawQuery("SHOW FULL TABLES LIKE '".$this->dbTable."'");
		if(!(count($tableResult)>0)) {
			$this->errorHandler->throwOne(Array("Database Error", "Table '{$this->dbTable}' does not exist.",500, true));
			$this->errorHandler->throwOne(20);
			exit;
		}
		$result = Array();
		try {
			$result = $this->db->get($this->dbTable, null, $this->cols);
		} 
		catch (Exception $e) {
			$this->errorHandler->throwOne(Array("Database Error", $this->db->getLastError(),500, true));
			$this->errorHandler->throwOne(20);
		}
		return $result;
	}

	private function _unsetHiddenProperties($item) {
		for ($i=0; $i < count($this->hiddenProperties); $i++) { 
			unset($item[$this->hiddenProperties[$i]]);
		}
		return $item;
	}

	private function _unsetHiddenPropertiesMultiple($items) {
		foreach ($items as &$item) {
			$item = $this->_unsetHiddenProperties($item);
		}
		return $items;
	}




	/*
	*	method _makeAssociativeFieldsArray(String $table, Array $fields) 
	*
	*	transferes the given array fields from 'name' to 'tablename.name name'
	*	this is needed in method 'getAll()', to get the field names ready for a sql-join,
	*	which is again needed to include hasMany 
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

// NOT USED YET. MIGHT BE TOO COMPLICATED. Now done as simple stdClass
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