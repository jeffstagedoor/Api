<?php
/**
*	Class LogDefaultConfig
*	
*	@author Jeff Frohner
*	@copyright Copyright (c) 2015
*	@license   private
*	@version   1.0.0
*
**/

namespace Jeff\Api\Log;

// These are the default classes that shall be used by LogConfig.php
Class LogDefaultConfig {
	private $dirName = 'apiLog';
	private $dbTable = "log";
	private $dbTableLogin = "loglogin";
	private $path;

	public static function values() {
		$values = new \stdClass();
		return $values;
	}

	public static function getPath() {
		if(empty(self::$path)) {
			return dirname(__FILE__).DIRECTORY_SEPARATOR.self::$dirName.DIRECTORY_SEPARATOR;
		} else {
			return self::$path;
		}
	}

	public static function setPath($path) {
		self::$path = $path;
	}

	public static function getDbTable() {
		return self::$dbTable;
	}

	public static function setDbTable($tableName) {
		self::$dbTable;
	}
}