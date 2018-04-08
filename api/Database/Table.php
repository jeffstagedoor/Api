<?php
/**
 * Table class
 */

namespace Jeff\Api\Database;

/**
 * Class Table
 *
 * @author Jeff Frohner <office@jefffrohner.com>
 * @version 1.0.0 initial implementation
 * @package Jeff\Api
 * @copyright Copyright (c) 2018
 */
Class Table {
	/** @var string the name of the table */
	private $name;
	/** @var Columns[] array of Columns */
	private $columns;
	/** @var string name of primary key */
	private $primaryKey;
	/** @var Keys[] array of keys */
	private $keys;

	/**
	 * Constructor
	 *
	 * Sets the given name and initializes columns & keys array
	 * 
	 * @param string $name designated name of the table
	 */
	public function __construct($name) {
		$this->name = $name;
		$this->columns = Array();
		$this->keys = Array();
	}

	/**
	 * Adds a column to the columns array
	 * @param Database\Column $column Instance of Column to add
	 */
	public function addColumn($column) {
		$this->columns[] = $column;
		return $this;
	}

	/**
	 * returns the columns-array
	 * @return Columns[] the array of Columns
	 */
	public function getColumns() {
		return $this->columns;
	}

	/**
	 * searches for a column in this Table and returns it
	 * @param  string $columnName name of the column to search for
	 * @return Column|NULL             the Column, if found. Or NULL if no matching column was found
	 */
	public function findColumn($columnName) {
		foreach ($this->columns as $column) {
			if($column->getName() === $columnName) {
				return $column;
			}
		}
		return NULL;
	}

	/**
	 * Adds a key to the keys array
	 * @param Database\Key $key Instance of Key to add
	 */
	public function addKey($key) {
		$this->keys[] = $key;
		return $this;
	}

	/**
	 * returns the keys array
	 * @return Database\Key[] all Keys in this Table as array
	 */
	public function getKeys() {
		return $this->keys;
	}

	/**
	 * returns a specific key in this table by name
	 * @param  string $name name of the key to find
	 * @return Database\Key|NULL a found key or NULL
	 */
	public function getKeyByName($name) {
		$keys = $this->getKeys();
		foreach ($keys as $key) {
			if($key->name === $name) {
				return $key;
			}
		}
		return NULL;
	}

	/**
	 * Sets primaryKey to given name
	 * @param string $primaryKey name of the primary key
	 */
	public function setPrimaryKey($primaryKey) {
		$this->primaryKey = $primaryKey;
		return $this;
	}
	/**
	 * Gets primaryKey to given name
	 * @return string $primaryKey name of the primary key
	 */
	public function getPrimaryKey() {
		return $this->primaryKey;
	}

	/**
	 * Generates an CREATE TABLE sql 
	 * @return string the generated sql
	 */
	public function getCreateTableSql() {
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
}