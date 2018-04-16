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
	public $dbKeys = [];

	// Relationships
	public $hasMany = [
		"comments" => [
				"sourceField"=>'post',
				"storeField"=>'comments', 
			],
		"accounts2posts"=> [
				"db"=> [
						['id', 'varchar', '20', false],
						['account', 'int','11', false],
						['post', 'int', '11', false],
						['modBy', 'int','11', true],
				],
				"primaryKey"=>"id",
				"sourceField"=>"post",
				"storeField"=>"accounts"
			],
		];
}
