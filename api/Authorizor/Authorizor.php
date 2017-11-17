<?php

namespace Jeff\Api\Authorizor;



Class Authorizor {

	private $db = NULL;
	private $account = NULL;

	/*
	*	Constructor
	*	@params: $account is an object of account-class
	*	gets a database object passed into
	*
	*/
	public function __construct($settings, $account, $db) {
		$this->settings = $settings;
		$this->account = $account;
		$this->db = $db;
	}

	public function authorize($modelName, $modelNamePlural, $id, $level) {
		if(isset($this->settings->{$modelName})) {
			#var_dump($Settings->{$this->modelName});

			if($this->settings->{$modelName}['relation']==='self') {
				// special case, the last (highest) level in inherited hierarchy
				$table = $modelNamePlural;
				$this->db->where('id', $id); // eg.: account
			} else {
				// standard case when walking up the inherited hierarchy
				$table = $this->settings->{$modelName}['relation']; // eg. accounts2productions
				$this->db->where($modelName, $id); // eg.: production
				$this->db->where('account', $this->account->id); // the account part of the relation			}
			}
			$relation = $this->db->getOne($table/*, Array('rights')*/);
			#var_dump($relation);
			$rights = isset($relation['rights']) ? $relation['rights'] : 0;
			$minRights = $this->settings->{$modelName}[$level]['minRights'];
			#echo "checking minRights: rights=$rights minRights=$minRights\n";
			if($rights>=$minRights) {
			#	echo "allowed, returning true\n";
				return true;
			} else {
			#	echo "NOT allowed -> checking in inherited:\n";
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
			#		echo "calling myself authorize now with:\n";
		// 			echo "
		// inheritedModelName: $inheritedModelName\n
		// inheritedModelNamePlural: $inheritedModelNamePlural\n
		// inheritedId: $inheritedId\n
		// inheritedLevel: $inheritedLevel\n";
					return $this->authorize($inheritedModelName, $inheritedModelNamePlural, $inheritedId, $inheritedLevel);
				} else {
					// no more inheritance found -> no not allowed
					return false;
				}
			}
		} else {
			// if there is no config for authrization, allow access:
			return true;
		}
	} 

}

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
