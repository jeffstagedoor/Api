<?php
/**
*	Classes DBHelper
*	
*	Helper functions for API and it's Database
*
*
*	@author Jeff Frohner
*	@copyright Copyright (c) 2017
*	@license   private
*	@version   1.0
*
**/
namespace Jeff\Api;

Class dbHelper {
	private $db;

	public function __construct($db) {
		$this->db = $db;
	}

	public function extractTableInfo($tableName) {
		$result = $this->db->rawQuery("DESCRIBE `".$tableName."`");

		// $tableInfo=$result;
		$tableInfo = array();
		// print_r($result);
		foreach ($result as $key => $value) {
			// print_r($value);
			$info = new \stdClass();
			$info->name = $value['Field'];
			$info->type = $this->_getColumnType($value['Type']);
			$info->length = $this->_getLength($value['Type']);
			$info->hasNull = $this->_getNull($value['Null']);
			$info->default = $this->_getDefault($value['Default']);
			$tableInfo[] = $info;
		}
		return $tableInfo;
	}

	public function getIndexes($tableName) {
		$result = $this->db->rawQuery("SHOW INDEX FROM `".$tableName."`");
		return $result;
		// print_r($result);
	}



	private function _getLength($info) {
		$pattern = '/\({1}([\d\W]*)\){1}/';
		preg_match($pattern, $info, $matches);
		return isset($matches[1]) ? $matches[1] : NULL;
	}

	private function _getColumnType($info) {
		$pattern = '/([\w]*)(\([\d\W]*\))*/';
		preg_match($pattern, $info, $matches);
		return isset($matches[1]) ? $matches[1] : NULL;
	}

	private function _getNull($info) {
		if($info==='NO') {
			return false;
		} else {
			return true;
		}
	}

	private function _getDefault($info) {
		if($info>='') {
			return $info;
		} else {
			return NULL;
		}
	}

}

/**
* several helper classes to create an sql statement to create tables
*
*
*/
Class dbTable {
	private $name;
	private $columns;
	private $primaryKey;
	private $keys;

	public function __construct($name) {
		$this->name = $name;
		$this->columns = Array();
		$this->keys = Array();
	}

	public function addColumn($column) {
		$this->columns[] = $column;
		return $this;
	}

	public function addKey($key) {
		$this->keys[] = $key;
		return $this;
	}

	public function setPrimaryKey($primaryKey) {
		$this->primaryKey = $primaryKey;
		return $this;
	}

	public function getSql() {
		$sql = "CREATE TABLE IF NOT EXISTS `$this->name` (\n";
		$s=Array();
		foreach ($this->columns as $key => $column) {
			$s[] = $column->getSql();
		}
		if($this->primaryKey) {
			$s[] = "PRIMARY KEY (`".$this->primaryKey."`)";
		}
		foreach ($this->keys as $key) {
			$s[] = $key->getSql();
		}
		return $sql.implode(",\n", $s)."\n)";
	}

	public function getColumns() {
		return $this->columns;
	}


}

Class dbColumn {
	private $name;
	private $type;
	private $length;
	private $hasNull;
	private $default;
	private $extra;

	/*
	*	takes one column-definition array
	*	OR $name, $type, $length, $hasNull, $default, $extra
	*/
	public function __construct() {
			$numargs = func_num_args();
			if($numargs===1 && is_array(func_get_arg(0))) {
				// we have an array with the column definition
				$column = func_get_arg(0);
			} elseif ($numargs===0) {
				throw new Exception("Error: Ivalid argument count in Class dbFlied. Must be an array or at least 2 column descriptors: name, type", 1);
			} else {
				$column = func_get_args();	
			}
			$this->name = $column[0];
			$this->type = $column[1];
			$this->length = isset($column[2]) ? $column[2] : null;
			$this->hasNull = isset($column[3]) ? $column[3] : false;
			$this->default = isset($column[4]) ? $column[4] : null;
			$this->extra = isset($column[5]) ? $column[5] : null;
	}

	public function getSql() {
		$s = '`'.$this->name.'`'.' '.$this->type;
		if($this->length) {
			$s.= '('.$this->length.')';
		}
		if(!$this->hasNull) {
			$s.= ' NOT NULL';
		}
		if($this->default!=null) {
			if(strtoupper($this->default)==='NULL') {
				$s.= ' DEFAULT NULL'; 

			} else {
				if (strtoupper($this->default)==='CURRENT_TIMESTAMP' && strtoupper($this->type)==='TIMESTAMP') {
						$s.= ' DEFAULT CURRENT_TIMESTAMP'; 
				} else { 
					$s.= ' DEFAULT \''.$this->default.'\'';
				}
			}
		}
		if($this->extra) {
			$s.= ' '.$this->extra;
		}

		return $s;
	}
}

Class dbKey {
	private $name;
	private $columns;

	public function __construct($name, Array $columns) {
		$this->name = $name;
		$this->columns = $columns;
	}

	public function getSql() {
		$s = "KEY `$this->name` (";
		$k = Array();
		foreach ($this->columns as $key => $value) {
			$k[] = "`$value`";
		}
		return $s.implode(',', $k).")";
	}
}