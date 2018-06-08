<?php
/**
*	Class Comments
*	
*	@author Jeff Frohner
*	@copyright Copyright (c) 2015
*	@license   private
*	@version   1.0
*
**/

namespace Jeff\Api\Models;

Class Comments extends Model
{

	// the Model Configuration
	public $modelName = 'comment';
	public $modelNamePlural = 'comments';

	public $searchSendCols = Array('id', 'date', 'amountTotal', 'note', 'recipient');	// what data (=db-fields) to send when querying a search 
	public $dbTable = "comments";

	public $dbDefinition = array( 	
			array('id', 'int', '11', false, null, 'AUTO_INCREMENT'),
			array('date', 'date', null, true),
			array('title', 'varchar', '50', false),
			array('body', 'varchar', '250', false, false),
			array('post', 'int', '11', false),
			array('modBy', 'int', '11', true),
		);
	public $dbPrimaryKey = 'id';
	public $dbKeys = [];
}
