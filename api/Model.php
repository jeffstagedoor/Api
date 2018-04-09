<?php
/**
 * This file contains class Model, the base class for all Models including Account
 */


namespace Jeff\Api\Models;
use Jeff\Api as Api;


/**
 *	This is the base class for all models.
 *	Includes getters and setters as far as they can be generalized
 *	This class shall be extended by consuming app to define the app-specific models.
 *	Many of the parameters and methods shall be overridden.
 *	
 *	@author Jeff Frohner
 *	@copyright Copyright (c) 2015-2016
 *	@license   private
 *	@version   1.8.1
 */
Class Model 
{

	/**
	* The name of the model. e.g. "Post", "Comment"
	*
	*  a modelName __MUST NOT__ include a '2', because otherwise it'll be treated as a relational-model.
	*  Shall be overridden
	* @var string The name of the model. e.g. "Post", "Comment"
	*/
	protected $modelName = null;
	
	/** @var string the plural version of $modelName */
	protected $modelNamePlural = null;
	
	/**
	*
	* Name of parent Model.
	* __Not used til now (2018)__
	* this will be needed to make a bubbleUp method, 
	* to determine all the mother-models of a grand-child model such as a track, 
	* that belongsTo an artistgroup, an production, a workgroup
	* will be needed to figure out if a user may edit/delete an item or not
	*
	* @var string Name of parent model
	*/
	protected $motherModel = null; 


	/**
	*
	* Database-Table definition to be used by {@see DBHelper} class to create the corresponding table
	* usually to be overriden in class that extends model-class.
	* an array with this specification:
	* 
	* ```
	* public $dbDefinition = Array(
	*		string $fieldName, 			// 'id', 'email', 'label',..
	*		string $fieldType, 			//	'int', 'varchar', 'timestamp',..
	*		string|NULL $fieldLength, 	// '11', '250', NULL
	*		boolean $canBeNull, 		// true, false
	*		mixed $defaultValue, 		// '', NULL, 
	*		string $special  			// 'auto_increment', 'on update CURRENT_TIMESTAMP'
	* );
	* ```
	* 
	* so a typical dbDefinition could look like this:
	* 
	* ```
	* public $dbDefinition = Array(
	* 		array ('id', 'int', '11', false, NULL, 'auto_increment'),
	* 		array ('email', 'varchar', '80', false),
	* 		array ('password', 'varchar', '250', true),
	* 		array ('rights', 'tinyint', '4', false, '0'),
	* 		array ('authToken', 'varchar', '250', true),
	* 		array ('refreshToken', 'varchar', '250', true),
	* 		array ('fullName', 'varchar', '80', false, ''),
	* 		array ('firstName', 'varchar', '20', false, ''),
	* 		array ('middleName', 'varchar', '20', false, ''),
	* 		array ('prefixName', 'varchar', '20', false, ''),
	* 		array ('lastName', 'varchar', '30', false, ''),
	* 		array ('profilePic', 'varchar', '100', false, ''),
	* 		array ('lastOnline', 'timestamp', null, true),
	* 		array ('lastLogin', 'timestamp', null, true),
	* 		array ('invitationToken', 'varchar', '250', true),
	* 		array ('invitedBy', 'int', '11', true),
	* 		array ('modDate', 'timestamp', NULL, false, 'CURRENT_TIMESTAMP', 'on update CURRENT_TIMESTAMP'),
	* 		array ('modBy', 'int', '11', true),
	* 	);
	* ```
	*
	* @var array $dbDefinition Database-Table definition
	* @see DBHelper
	* 
	*/
	protected $dbDefinition = array();


	/** @var string field-name in which api will store the last account-id that changed/created an item. Can be NULL */
	public $modifiedByField = 'modBy';
	/** @var boolean Models with isSortable=true MUST have a field 'sort' in dbTable */
	public $isSortable = false;
	/** @var string If a model is sortable it usually has a parent-model as group 
	*               For example if you want comments to be sortable, then this would be 'post' - the reference to the parent
	*/
	public $sortBy = 'referenceField';


	/** 
	* @var string typically 'role' or 'rights'
	* many2many-references often have a value that describe that relationship
	*/
	public $many2manyParam = 'role';


	// DB-vars & Object
	/** @var \MySqliDb Instance of Database class */
	protected $db = null;

	/**
	* @var string $dbTable The name of the corresponding database table
	*/
	protected $dbTable = 'undefined';

	/**
	* @var string $dbIdField Database-Fieldname in which the id is stored. Usually (and by default) 'id'
	*/
	protected $dbIdField = 'id';

	/**
	*	@var string primary database id/key.
	*	usually/per default 'id'
	*/	
	public $dbPrimaryKey = 'id';

	/**
	 * the database keys/indexes definition which shall look like that:
	*	           
	*	           ```
	*	           array(
	*	               "name" => "firstIndex",
	*	               "collation" => "A",
	*	               "cardinality" => 5,
	*	               "type" => "BTREE",
	*	               "comment" => "This is a database index foo bar, whatsoever",
	*	               "columns" => ["fieldName1", "anotherField"]
	*	           )
	*	           ```
	*	
	* @var array   the database keys/indexes definition, 
	*/	
	public $dbKeys = [];

	/** @var array An array of all database-columns. Set in `__constructor` via `_makeAssociativeFieldsArray()` */
	public $cols = null;
	/** @var int When a new item of model is added, this param will have it's newly created id */
	public $lastInsertedID = -1;
	/** @var object When adding sideload to the request load, they will live here 
	* 
	* ```
	* $sideload->otherModelName->data[]
	* ```
	* 
	*/
	public $sideload = null;

	/** 
	* Array of field-names that shall be masked with *** when sent to client according to it's properties 
	* 
	* ```
	* Array(
	*    'email' => Jeff\Api\DataMasker::MASK_TYPE_EMAIL,
	*    'tel' => Jeff\Api\DataMasker::MASK_TYPE_TEL,
	* );
	* ```
	* @var array 
	* 
	* @see \Jeff\Api\DataMasker
	*/
	protected $maskFields = Array();

	/** 
	* @var string[] what data (=db-fields) to send when querying a search 
	*/
	protected $searchSendCols = Array('id');	

	/** 
	* @var string[] hiddenProperties
	* array of properties (=db-fieldNames) that will be unset before sending the payload.
	* allways remove password, authToken, auth, refreshToken.
	* either push items or override
	*/
	protected $hiddenProperties = Array('password', 'authToken', 'auth', 'refreshToken');

	/**
	* What fields shall be validated before inserting/updating.
	* 
	* ```
	* protected $validateFields = Array(
	* 	Array('field' => 'email', 'valtype' => Jeff\Api\Validate::VAL_TYPE_EMAIL, 'arg' => null),
	* 	Array('field' => 'description', 'valtype' => Jeff\Api\Validate::VAL_TYPE_LENGTH_MAX, 'arg' => 300),
	* 	Array('field' => 'description', 'valtype' => Jeff\Api\Validate::VAL_TYPE_LENGTH_MIN, 'arg' => 300),
	* );
	* ```
	* 
	* @var array[]
	* @see Jeff\Api\Validate
	*/
	protected $validateFields = Array();

	/**
	* list of db fields that shall not be updated in a normal api-update. 
	* Those have to be set via task (or special account-api calls such as 'signin')
	* An example is those values for the account-model: `['email','password','authToken', 'invitationToken', 'lastOnline', 'lastLogin', 'signin']` 
	*/
	protected $doNotUpdateFields = [];


	/**
	* Sets n to many relations.
	* There are two types of relations possible. A one to many and a many2many (via a sub-Table).
	* This property is an array of those relationships.      
	* Shall be overriden by consuming app and it's models.
	*
	* Relations defined that way will be included to the payload as array of ids:
	* `comments: [4,7,98],`
	*
	* The **one to many** relationship can be defined like so:
	*
	* ```
	* "comments"=> Array(
	*                "sourceField"=>"post",
	*                "storeField"=>"comments"
	*		       )
	* ```
	*
	* This assumes: 
	* - the model 'comments' has a db-field 'post' as reference to the post.id
	* - the model 'posts' does _not_ have a db-field 'comments'
	*
	* **many to many relationships**:
	* have a name by convention 'accounts2posts', 'account2comments',.. all in plural
	* and are defined on the _left side_ (accounts) as _one to many_ relationship:
	*
	* ```
	* "accounts2posts" => Array(
	*	                       "sourceField"=>"account",
	*	                       "storeField"=>"accounts2posts"
	*	                    ),
	* ```
	*
	* whereas on the right side we need to have the complete db-definition:
	*
	* ```
	* "accounts2posts" => Array(     	// the key is also the database table name
    *       "db"=>array(			// just like a 'normal' dbDefinition
	*				array('id', 'varchar', '20', false),
	*				array('account', 'int','11', false), 	// = sourceField on left side
	*				array('post', 'int', '11', false),		// = sourceField on right side
	*				array('modBy', 'int','11', true),
	*		),
	*		"primaryKey"=>"id",
	*		"sourceField"=>"post",
	*		"storeField"=>"accounts"
	* ),
	* ```
	*/
	protected $hasMany = Array ();

	/**
	* what Items to send as sideload when doing a simple `getOneById()`.
	* sideloadItems can be added for all hasMany-fields that are defined
	*
	* Example:
	*
	* ```
	* protected $sideloadItems = Array(
	*    Array("name"=>'accounts2productions',"orderBy"=>'rights', "orderDirection"=>'desc'),
	*    Array("name"=>'artistgroups',"orderBy"=>null, "orderDirection"=>null),
	*    Array("name"=>'events', "orderBy"=>'start', "orderDirection"=>"asc"),
	* );
	* 
	*/
	protected $sideloadItems = Array();

	/**
	* A list of methods that can be called via special api call `www.example.com/api/modelname/specialMethod`.
	* Unless the method is listed here it won't be called (for security)
	* @var array
	*/
	public $specialMethods = Array();

	// CONSTANTS
	const SEARCH_TYPE_STRICT = "strict";
	const SEARCH_TYPE_SEMISTRICT = "semistrict";
	const SEARCH_TYPE_LOOSE = "loose";
	const SEARCH_TYPE_VERYLOOSE = "veryloose";
	const SEARCH_MIN_LENGTH = 4;


	/**
	* Constructor
	*
	* - sets the passed objects/instances 
	* - generates the `$cols` from `$dbDefinition`
	*
	* Possible Hook: initializeHook()
	*
	* This can be usefull to alter the dbDefinition of Account class, but also for many other things.
	* Example of an implementation (in Accounts.php):
	*
	* ```
	* protected function initializeHook() {
 	*    $this->dbDefinition[] = array ('artist', 'int', '11', true);
  	* }
  	* ```
  	*
  	* @param \MySqlDb $db Instance of Database class
  	* @param \Jeff\Api\Environment $ENV Instance of Environment class
  	* @param \Jeff\Api\ErrorHandler $errorHandler Instance of ErrorHandler class
  	* @param Account $account Instance of Account class (with the logged in account)
  	* @param object $request Object containing all relevant infos about the current request
	*/
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

	/**
	*	alias for getOneById(id) and getAll()
	* 
	* @param int $id If set, the id of the item to get. If not set, all items will be returned (`getAll()` will be called)
	* @return array The found item or items
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

	/**
	* Gets one item of the model by a given id
	*
	* Checks for Authorization first.
	*
	* There might be a hook provided in future versions, but it's _not yet implemented_:
	* ```
	* function beforeGetOne($id) {
	* 		//global $Account;
	* 		//$restriction = new ModelRestriction('REF_ID', 'account', $Account->id);
	*			$restrictions = Array();
	*			$restrictions[] = $restriction;
	*			return $restrictions;
	* }
	* ```	
	*
	* @param int $id The id of the requested item
	* @return object|boolean The dataset if successfull, false if the item wasn't found
	* @see \Jeff\Api\Authorizor\Authorizor
	*/
	public function getOneById($id) 
	{
		// checking for quthorization first:
		if( isset($this->request->type) && $this->request->type!=Api\Api::REQUEST_TYPE_SPECIAL) {
			if(file_exists($this->ENV->dirs->appRoot."AuthorizationConfig.php")) {
				include_once($this->ENV->dirs->appRoot."AuthorizationConfig.php");
				$Authorizor = new \Jeff\Api\Authorizor\Authorizor($Settings, $this->account, $this->db);
				// check if we have settings for that model
				$isAuthorized = $Authorizor->authorize($this->modelName, $this->modelNamePlural, $id, 'mayView');
				if(!$isAuthorized) {
					$this->errorHandler->throwOne(Array('Not allowed', 'You are not allowed to access this recource', 400, \Jeff\Api\ErrorHandler::CRITICAL_LOG,false));
					exit;
				}
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

	
	/**
	* Gets all items of the model
	*
	* - adds hasMany fields for each item
	* - adds the sideload for each item
	* 
	* The extending Model shall implement a beforeGetAll-Hook to limit the result to only the items the account is allowed to see.
	*
	* Standard Example for such a hook: 
	*	
	*	```
	*	function beforeGetAll() {
	*			$restrictions = new stdClass();
	*			$restriction->type = 'ID_IN';    // possible types are: 'ID_IN', 'REF_IS', 'REF_IN', 'LIMIT'
	*			// if type='ID_IN':
	*			$restriction->data = array(1,2,3);
	*
	*			// if type='REF_IS':
	*			$restriction->referenceField = 'post';
	*			$restriction->id = 1;
	*
	*			// if type='REF_IN':
	*			$restriction->referenceField = 'post';
	*			$restriction->data = array(1,2,3);
	*
	*			$restrictions[] = $restriction;
	*			// repeat steps above for more restrictions
	*			return $restrictions;
	*	}
	*	```
	*
	* @param array|null $filters filter will come as an array: `[{key: 'nameofthefield', value: 'testvalue', comp: '='}]`
	*                            defaults to null, so no filter is applied if omitted
	*/
	
	public function getAll($filters=null) 
	{

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
		$items = $this->_unsetHiddenPropertiesMultiple($items);
		$items = $this->_addHasManyMultiple($items);
		$this->_addSideloadsMultiple($items); 			// add sideloads, defined in Child-Model Class as $sideloadItems
		return $items;
	}

	/**
	* Getter for coalesce GET calls
	*
	* If we get an array of ids, as they arrive when doing an coalesceFindRecord call (in ember RestAdapter 'coalesceFindRequests: true')
	* we return only the corresponding items 
	*	
	* @param array $coalesceIds
	* @return array The found items, or false if an error occurred
	*/
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
	* MANY TO MANY Relationships
	*
	* this depends on following conventions:
	* db-tables/models that represent a manyToMany Relationship have this name/structure:
	* - dbTable= 'needles2haystacks'; e.g. 'accounts2posts' (both plural)
	* - id-field = needle_id + '_' + haystack_id (eg 2_15)
	*
	* @param string $id
	* @param string $modelLeftNamePlural
	* @param string $by
	* @param array|null $filters
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



	/**
	* Updates a many to many relationsship
	*
	* expected call to api would be a PUT to 
	* ../api/artists2posts/1_2 
	* ../api/leftModel2rightModel/id with a fitting dataset in body
	*
	* This api-call will result in in a call of this method on the _right_ model (posts in this case).
	* This is why the dbDefinition of the relationship is defined also on the right model side of the relationship.
	*
	*
	* possible HOOK:
	*
	* ```
	* public function beforeUpdateMany2Many($id, $what, $data) {
	*    return $data; // has to return the (changed) dataset
	* }
	* ```
	*
	* @param Model $modelLeft the left model of that replationship
	* @param int $id of the relationship
	* @param array $data the dataset (coming from request body) to be stored
	*/
	public function updateMany2Many($modelLeft, $id, $data) {
		// call the hook beforeUpdateMany2Many if it exists:
		if(method_exists($this, 'beforeUpdateMany2Many')) {
			$data = $this->beforeUpdateMany2Many($modelLeft, $id, $data);
		}

		$this->db->where('id', $id);
		$success = $this->db->update($modelLeft->modelNamePlural.'2'.$this->modelNamePlural, $data);
		return ($success) ?  $id : false;
	}


	/**
	*
	* Deletes a many 2 many relationship
	* 
	* expected call to api would be a DELETE to 
	* ../api/artists2workgroups/1_2 
	*
	* This api-call will result in in a call of this method on the _right_ model (workgroups in this case).
	* This is why the dbDefinition of the relationship is defined also on the right model side of the relationship.
	*
	* possible HOOK, that must return a boolean - if the deletion shall be executed or not:
	+ 
	* ```
	*  public function beforedeleteMany2Many($modelLeft, $id) {
	* 	   return $true;
	*  }
	* ```
	*
	* @param Model $modelLeft Model class of the left model (of relationship account2post -> account)
	* @param string $id 
	* @return int|boolean returns the id of deleted relationship or false if an error occurred
	* 
	*/
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



	/**
	* returns the last inserted id after a new item was added
	* @return int 
	*/
	public function getLastInsertedId() {
		return $this->lastInsertedID;
	}



	/**
	* Searches for an item of current model.
	* The api call would be `../api/modelName/search` with a dataset like
	*	
	* ```
	* {
	*	key1: term1,
	*   key2: term2,
	*   ....
	*   condition: 'or', // possible: 'and', 'or'
	*   searchType: 'loose', // possible: 'strict', 'semistrict', 'loose', 'veryloose'
	*   restrictions: { key5: value5, key6: value7 }
	* }
	* ```
	* 
	* searchTypes:
	* - strict: will result in a `where foo='bar'`
	* - semistrict: will result in a `where foo LIKE 'bar'` (right now it's the same as loose)
	* - loose: will result in a `where foo LIKE 'bar'` (right now it's the same as semistrict)
	* - veryloose: will result in a `where foo LIKE '%bar%'`
	* 
	* @param object $data The dataset with all the info required for a search
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
				$value=preg_replace('/[ \-_\´\`\']/', "%", $value);
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

	/**
	* Returns number of items by given delimiters
	*
	* @param object|null $delimiters And object of key-value pairs for realtions
	*                          f.e. to get a count for the comments of a post
	*                          the object would be 
	*                          `{post: 2}`
	* @return int Number of items	
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
	*	Adds a new item of Model
	* 
	*	possible Hooks: beforeAdd($data), afterAdd($data, $id)
	* 	@param array $data	
	*	@return int the new id of the item, false if an error occurred
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


	/**
	* Adds a many to many relationsship
	*
	* expected call to api would be a POST to 
	* ../api/artists2posts
	* ../api/leftModel2rightModel with a fitting dataset in body
	*
	* This api-call will result in in a call of this method on the _right_ model (posts in this case).
	* This is why the dbDefinition of the relationship is defined also on the right model side of the relationship.
	*
	* possible HOOK:
	*
	* ```
	* public function beforeAddMany2Many($what, $data) {
	*    return $data; // has to return the (changed) dataset
	* }
	* ```
	*
	* @param Model $modelLeft the left model of that replationship
	* @param array $data the dataset (coming from request body) to be stored
	* @return int|boolean The id of the newly created relationship or false on failur
	*/
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
			$this->errorHandler->add(Array("DB-Error", "could not get relationTable '".$relationTableName."'", 500, Api\ErrorHandler::CRITICAL_EMAIL, true, $e));
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
				$this->errorHandler->throwOne(array('API-Error','Relation already existst',400, Api\ErrorHandler::CRITICAL_LOG, false));
				exit;
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

	/**
	* Adds multiple items of the model.
	* Model __MUST__ implement method `addMultipleHook($data, $multipleParams)`
	*
	* @param array $data
	* @param object $multipleParams
	* @return array of the added items
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


	/**
	* Standard update of a model item
	*	
	* @param int $id The id of the item
	* @param object|array $data Dataset to be updated to
	* @return object|boolean Object containing a count of the updated items and the ids - or fals on failur
	*/
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


	/**
	* Standard delete of a model item
	*	
	* @param int $id The id of the item
	* @return int Object containing a count of the deleted items
	*/
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


	/**
	* Method to import an item from one parent to another.
	* This method is not implemented in base model, therefor needs to be overridden in expanding model of consuming app.
	* See source (Model.php) for an (quite standard) example implementation
	*
	* @param object $data A dataset of all necessary info to fulfil that import.
	*                     could be something like:
	* ```
	* { 
	*    id: 1,                   // id of the item to import
	*    targetId: 1,                 // id of the parent model to import to
	*    targetReference: 'workgroup' // name of the parent model to import to
	* }
	* ```
	* 
	*/
	public function import($data) {
		echo "You've made a special POST call to {$this->modelName}, but import is not implemented.\n";
		echo "Method import() needs to be overridden in consuming app, in the expanding model.\n";

		// EXAMPLE implementation, that might work for most Model-Types.
		// will be buggy, if the hasMany-Items have hasMany-Items themselves...
		/**
		$this->db->startTransaction();
		// 1. get the source
		if(!isset($data->id)) {
			$this->errorHandler->throwOne(41);
			exit;
		}
		$source = $this->getOneById($data->id);
		
		// 2. change the item to prepare to be saved (workgroup it is here)
		// dublicate, manipulate and save:
		$newItem = $source;
		$newItem[$data->targetReference] = $data->targetId; // re-set reference to new target
		unset($newItem['id']); // we don't need the id to insert as new item
		unset($newItem['modDate']); // has auto-update in db-def
		$newItem['modBy'] = $this->account->id;
		// hasMany fields unsetten
		foreach ($this->hasMany as $hmName => $hmInfo) {
			unset($newItem[$hmInfo['storeField']]);
		}

		// 3. save the item
		$newId = $this->db->insert($this->dbTable, $newItem);
		if(!$newId) {
			$this->errorHandler->throwOne(22);
			$this->errorHandler->throwOne(array("DB-Error", "The query: ".$this->db->getLastQuery() ."\nfailed with error: ".$this->db->getLastError()."\nin ".__FILE__.":".get_class()." - ".__LINE__,500, Api\ErrorHandler::CRITICAL_EMAIL,true));
			$this->db->rollback();
			exit;
		}


		// 3. get the hasMany fields. In this case it's only the locations, but still....lotta work
		$hm = [];
		foreach ($this->hasMany as $hmName => $hmInfo) {
			$this->db->where('id', $source[$hmInfo['storeField']], 'IN');
			$items = $this->db->get($hmName);
			foreach ($items as $index => &$item) {
				unset($item['id']);
				$item[$hmInfo['sourceField']] = $newId;
				unset($item['modDate']);
				$item['modBy'] = $this->account->id;

				// insert to database
				$item['id'] = $this->db->insert($hmName, $item);
				if(!$item['id']) {
					$this->errorHandler->throwOne(22);
					$this->errorHandler->throwOne(array("DB-Error", "The query: ".$this->db->getLastQuery() ."\nfailed with error: ".$this->db->getLastError()."\nin ".__FILE__.":".get_class()." - ".__LINE__,500, Api\ErrorHandler::CRITICAL_EMAIL,true));
					$this->db->rollback();
					exit;
				}
			}
			
		}	
		// $this->db->rollback();
		$this->db->commit();
		return $this->getOneById($newId);
		*/
	}


	/** 
	* returns the database table name of this model
	* @return string dbTable
	*/
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
	/** 
	* private helper that executes the prepared db-get.
	* - checking first if table exists
	* - throwing error if query was not successfull
	* 
	* @return array The resultset
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

	/**
	* private helper that unsets the hidden properties defined in $hiddenProperties for one item and returnes the changes item
	*
	* @param array $item one item of the model
	* @return array the changed item
	* 
	*/
	private function _unsetHiddenProperties($item) {
		for ($i=0; $i < count($this->hiddenProperties); $i++) { 
			unset($item[$this->hiddenProperties[$i]]);
		}
		return $item;
	}

	/**
	* private helper that unsets the hidden properties defined in $hiddenProperties for all given item and returnes the changes items
	*
	* @param array[] $items array of items of the model
	* @return array the changed items
	*/
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