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
Class Column {
	/** @var string name of the column */
	private $name;
	/** @var string the column type */
	private $type;
	/** @var int|double length desired length */
	private $length;
	/** @var bool if the column may be null */
	private $hasNull;
	/** @var string|bool the default value */
	private $default;
	/** @var string extra definitions like 'AUTO_INCREMENT' */
	private $extra;

	/**
	 * creates the column definition.
	 * Takes either one array `[name, type, length, hasNull, default, extra]`
	 * or each param on its own (in this order)
	 */
	public function __construct() {
			$numargs = func_num_args();
			if($numargs===1 && is_array(func_get_arg(0))) {
				// we have an array with the column definition
				$column = func_get_arg(0);
			} elseif ($numargs===0) {
				throw new Exception("Error: Ivalid argument count in Class dbField. Must be an array or at least 2 column descriptors: name, type", 1);
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

	/**
	 * return property name
	 * @return string Name of the Column
	 */
	public function getName() {
		return $this->name;
	}
	/**
	 * return property Type
	 * @return string Type of the Column
	 */
	public function getType() {
		return $this->type;
	}
	/**
	 * return property Length
	 * @return string Length of the Column
	 */
	public function getLength() {
		return $this->length;
	}
	/**
	 * return property hasNull
	 * @return string hasNull of the Column
	 */
	public function getHasNull() {
		return $this->hasNull;
	}
	/**
	 * return property Default
	 * @return string Default of the Column
	 */
	public function getDefault() {
		return $this->default;
	}
	/**
	 * return property Extra
	 * @return string Extra of the Column
	 */
	public function getExtra() {
		return $this->extra;
	}

	/**
	 * returns a sql-part to create/alter a column (withuot trailing ',')
	 * @return string sql part
	 */
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
				} 
				// elseif(strtoupper($this->default)==='CURRENT_DATE' && strtoupper($this->type)==='DATE') {
				// 		$s.= ' DEFAULT CURRENT_DATE'; 
				// }
				else { 
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