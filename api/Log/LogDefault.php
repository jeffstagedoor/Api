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
	protected static $dirName = 'apiLog';
	protected static $dbTable = "log";
	protected static $dbTableLogin = "loglogin";
	protected static $path;

	public static function values() {
		$values = new \stdClass();
		return $values;
	}

	public static function getPath() {
		return static::$path;
	}

	public static function setPath($path) {
		static::$path = $path;
	}

	public static function getDbTable() {
		return static::$dbTable;
	}

	public static function setDbTable($tableName) {
		static::$dbTable;
	}
}