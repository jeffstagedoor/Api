<?php
/**
 * This file contains the class Authorizor
 */

namespace Jeff\Api\Authorizor;
use Jeff\Api;



/**
* A class to aothorize a request of a logged in user/account to a specific model item.
* 
* To make this work there has to be a AthorizationConfig.php implemented in consuming app.
* This AuthorizationConfig lookls like this:
* 
* ```
* <?php 
* namespace Jeff\Api\Authorizor;
* 
* $Settings = new \stdClass();
* $Settings->production = [
*	"default"=>\Constants::PRODUCTIONS_ARTIST,
*	"relation"=>"accounts2productions",
*	"mayView"=> [ 
*				"minRights"=>\Constants::PRODUCTIONS_ARTIST,
*				    // if this model (productions) has a parent model, this setting may include an 'inherited' key
*				    // if the current account does NOT have a direct connection to the requested production
*				    // this authorizor will have look to the inherited model (workgroup) to check there
*				"inherited"=> ["modelName"=>"workgroup", "modelNamePlural"=>"workgroups", "level"=>"mayWork"]
*		
*				],
*	"mayWork"=> [ 
*				"minRights"=>\Constants::PRODUCTIONS_HODT,
*				"inherited"=> ["modelName"=>"workgroup", "modelNamePlural"=>"workgroups", "level"=>"mayEdit"]
*				],
*	"mayEdit"=> [ 
*				"minRights"=>\Constants::PRODUCTIONS_CM,
*				"inherited"=> ["modelName"=>"workgroup", "modelNamePlural"=>"workgroups", "level"=>"mayEdit"]
*				],
*
*	];
* ```
*/
Class Authorizor {

	/** @var \MySqliDb Instance of database class */
	private $db = NULL;
	/** @var Models\Account Instance of current Account */
	private $account = NULL;

	/**
	* The Constructor
	*
	* only sets the passed in params
	* 
	* @param object $settings        Object with the authorization settings.
	* @param Models\Account $account The current logged in account
	* @param \MysqliDb $db           Instance of Database Object
	*/
	public function __construct($settings, $account, $db) {
		$this->settings = $settings;
		$this->account = $account;
		$this->db = $db;
	}

	/**
	 * Authorizes an specific action on a given model item
	 *
	 * 
	 * @param  string  $modelName       The model to check for
	 * @param  string  $modelNamePlural the same model's plural name
	 * @param  int     $id              the items id
	 * @param  string  $level           'mayEdit'|'mayWork'|'maySee' - anything defined in AuthorizationConfig.php of consuming app
	 * @return bool                     If the current user is allowed to whatever comes as $level for given item ($id)
	 */
	public function authorize($modelName, $modelNamePlural, $id, $level) {
		if(isset($this->settings->{$modelName})) {

			if($this->settings->{$modelName}['relation']==='self') {
				// special case, the last (highest) level in inherited hierarchy
				$table = $modelNamePlural;
				$this->db->where('id', $id); // eg.: account
			} else {
				// standard case when walking up the inherited hierarchy
				$table = $this->settings->{$modelName}['relation']; // eg. accounts2productions
				$this->db->where($modelName, $id); // eg.: production
				$this->db->where('account', $this->account->id); // the account part of the relation
			}

			$relation = $this->db->getOne($table/*, Array('rights')*/);
			$rights = isset($relation['rights']) ? $relation['rights'] : 0;
			$minRights = $this->settings->{$modelName}[$level]['minRights'];
			
			if($rights>=$minRights) {
				// echo "allowed, returning true\n";
				return true;
			} else {
				// echo "NOT allowed -> checking in inherited:\n";
				if(isset($this->settings->{$modelName}[$level]['inherited'])) {
					$inheritedModelName = $this->settings->{$modelName}[$level]['inherited']['modelName'];
					$inheritedModelNamePlural = $this->settings->{$modelName}[$level]['inherited']['modelNamePlural'];
					$inheritedLevel = $this->settings->{$modelName}[$level]['inherited']['level'];
					// find "parent" id. The parent is the 'type' in inherited
					if($inheritedModelName==='account') {
						$inheritedId = $this->account->id;
					} else {
						$this->db->where('id', $id);
						$inheritedItem = $this->db->getOne($modelNamePlural,Array($inheritedModelName));
						$inheritedId = $inheritedItem[$inheritedModelName];
					}
					// echo "calling myself authorize now with:\n";
					// echo "
					// inheritedModelName: $inheritedModelName\n
					// inheritedModelNamePlural: $inheritedModelNamePlural\n
					// inheritedId: $inheritedId\n
					// inheritedLevel: $inheritedLevel\n";
					return $this->authorize($inheritedModelName, $inheritedModelNamePlural, $inheritedId, $inheritedLevel);
				} else {
					// no more inheritance found -> not allowed
					return false;
				}
			}
		} else {
			// if there is no config for authrization, allow access:
			return true;
		}
	} 

	/**
	* method to determine weather the current user is allowed to minipulate/accept/reject a given task
	* @param object $task db return object of the task
	* @return boolean if successfull
	*
	**/
	public function authorizeTask($task) {
		// check authorization: if the current user is allowed to minipulate/accept/reject a given task
		$isAllowed=false;
		// 1. check if user has sufficiant rights in one of the two (C or B) references 
		// (accounts2workgroups, accounts2productions,..) demanded:
		if($this->settings->C) {
			$this->db->where('account', $this->account->data->id);
			$this->db->where($this->settings->C->reference, $task->forB);
			$ref = $this->db->getOne($this->settings->C->referenceTable, Array('id', 'rights'));
			if($ref) {
				if($ref['rights']>=$task->forBRights) {
					$isAllowed = true;
				}
			}
		}
		if($this->settings->B) {
			$this->db->where('account', $this->account->data->id);
			$this->db->where($this->settings->B->reference, $task->forB);
			$ref = $this->db->getOne($this->settings->B->referenceTable, Array('id', 'rights'));
			if($ref) {
				if($ref['rights']>=$task->forBRights) {
					$isAllowed = true;
				}
			}
		}
		// 2. check if user has at least the accountRights demanded (will be admin for most times)
		if($this->account->data->rights>=$task->forAccountRights) {
			$isAllowed = true;
		}
		return $isAllowed;
	}

}

/*
 NOT USED (4/2018)

Class RightsGroup {
	private $items = Array();

	public function __construct() {

	}

	public function addItem(RightsItem $item) {
		$this->items[]=$item;
	}
}


Class RightsItem {
	private $code=0;
	private $label="default";
	private $css="primary";

	public function __construct($mixed, $label=null, $css=null) {
		if(is_array($mixed)) {
			// expecting array(code, label, css)
			list($code, $label, $css) = $mixed;
		} else {
			$code=$mixed;
		}
		$this->code = $code;
		$this->label = $label;
		$this->css = $css;

	}
}
*/