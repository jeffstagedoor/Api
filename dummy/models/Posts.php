<?php
/**
*	Class Posts
*	
*	@author Jeff Frohner
*	@copyright Copyright (c) 2015
*	@license   private
*	@version   1.0
*
**/

namespace Jeff\Api\Models;

Class Posts extends Model
{

	// the Model Configuration
	public $modelName = 'post';
	public $modelNamePlural = 'posts';
	private $hasMany = Array (
		Array("hasManyName"=>'comments',
				"dbTable"=>'comments', 
				"dbTargetFieldName"=>'post', 
				"dbSourceFieldName"=>'post', 
				"saveToStoreField"=>'comments', 
				"saveToStoreName"=>'comments'
		),
	);

	// DATABASE
	public $dbTable = "posts";
	public $dbDefinition = array( 	
			array('id', 'int', '11', false, null, 'AUTO_INCREMENT'),
			array('date', 'date', null, true),
			array('title', 'varchar', '50', false),
			array('body', 'varchar', '250', false, false),
			array('modBy', 'int', '11', true),
		);
	public $dbPrimaryKey = 'id';
	public $dbKeys = array(
		array('date', array('date')),
		array('text', array('title', 'body')),
		);
}