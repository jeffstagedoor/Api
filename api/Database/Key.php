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
	/** @var string[] array of included columns (as string) */
	private $columns;

	/**
	 * Constructor
	 *
	 * sets the name and the columns
	 * @param string $name    name of the key
	 * @param Array  $columns the included columns as array
	 */
	public function __construct($name, Array $columns) {
		$this->name = $name;
		$this->columns = $columns;
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