<?php
/**
 * Key Class
 */

namespace Jeff\Api\Database;

/**
 * Class Key
 *
 * @author Jeff Frohner <office@jefffrohner.com>
 * @version 1.0.0 initial implementation
 * @package Jeff\Api
 * @copyright Copyright (c) 2018
 */
Class Key {
	/** @var string name of the key */
	private $name;
	/** @var string type of the key */
	private $type;
	/** @var string collation of the key */
	private $collation;
	/** @var string cardinality of the key (what'S that??) */
	private $cardinality;
	/** @var string comment */
	private $comment;
	/** @var string[] array of included columns (as string) */
	private $columns;

	/**
	 * Constructor
	 *
	 * sets the name and the columns
	 * @param string $name    name of the key
	 * @param Object|Array $keyInfo an object|array, that describes the key
	 */
	public function __construct($name, $keyInfo) {
		$this->name = $name;
		if(is_array($keyInfo)) {
			$this->collation = isset($keyInfo['collation']) ? $keyInfo['collation'] : NULL;
			$this->cardinality = isset($keyInfo['cardinality']) ? $keyInfo['cardinality'] : NULL;
			$this->type = isset($keyInfo['type']) ? $keyInfo['type'] : NULL;
			$this->comment = isset($keyInfo['comment']) ? $keyInfo['comment'] : '';
			$this->columns = isset($keyInfo['columns']) ? $keyInfo['columns'] : [];
		}
		if(is_object($keyInfo)) {
			$this->collation = isset($keyInfo->collation) ? $keyInfo->collation : NULL;
			$this->cardinality = isset($keyInfo->cardinality) ? $keyInfo->cardinality : NULL;
			$this->type = isset($keyInfo->type) ? $keyInfo->type : NULL;
			$this->comment = isset($keyInfo->comment) ? $keyInfo->comment : '';
			$this->columns = isset($keyInfo->columns) ? $keyInfo->columns : [];
		}
	}

	/**
	 * returns the name of the key
	 * @return string name of the key
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * returns the part of sql string to create the key
	 * @return string sql part
	 */
	public function getSql() {
		$s = "KEY `$this->name` (";
		$k = Array();
		foreach ($this->columns as $key => $value) {
			$k[] = "`$value`";
		}
		return $s.implode(',', $k).")";
	}
}