<?php
/**
*	Class Accounts
*	
*	@author Jeff Frohner
*	@copyright Copyright (c) 2015
*	@license   private
*	@version   1.0
*
**/

namespace Jeff\Api\Models;
use Jeff\Api as Api;

Class Accounts extends Account 
{

	public $hasMany = Array(
		"accounts2posts" => Array(
				"sourceField"=>"account",
				"storeField"=>"accounts2posts"
			),
		"accounts2comments" => Array(
				"sourceField"=>"account",
				"storeField"=>"accounts2comments"
			),
	);

	// protected function initializeHook() {
	// 	$this->dbDefinition[] = array ('artist', 'int', '11', true);
	// }
}
