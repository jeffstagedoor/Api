<?php

/**
 * an abstract class, that shall be extended by any class that needs a 1-1 DBTable representation. 
 * This would be a (Base-) Model, the Log. This class defines/defaults all params that are nessecary to use the Table class.
 * 
 */
namespace Jeff\Api\Database;

abstract class DBTableRepresentation {

    /** @var string $dbTable The name of the corresponding database table */
    protected $dbTable = "default";
    
    /**
	*
	* Database-Table definition to be used by {@see Table} class to create the corresponding table.
	* @see Models\Model for specs
	*/
    public $dbDefinition = Array();

    /** @var string $dbPrimaryKey primary database id/key. */	
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
    
	/**
	 * returns the db-tableName
	 * @return string name of the corresponding database table
	 */
	public function getDbTable() {
		return $this->dbTable;
	}

}