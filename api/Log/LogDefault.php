<?php
/**
*	Class LogDefault* Config, For, Meta
*	
*	@author Jeff Frohner
*	@copyright Copyright (c) 2015
*	@license   private
*	@version   1.0.0
*
**/

namespace Jeff\Api\Log;
use Jeff\Api;


// These are the default classes that shall be used by LogConfig.php
Class LogDefaultConfig {
	const PATH = 'apiLog';
	const DB_TABLE = "log";
	const DB_TABLE_LOGIN = "loglogin";

	public static function values() {
		$values = new \stdClass();
		return $values;
	}

	public static function getPath() {
		return dirname(__FILE__).DIRECTORY_SEPARATOR.self::PATH.DIRECTORY_SEPARATOR;;
	}

	public static function getDbTable() {
		return self::DB_TABLE;
	}
}


Class LogDefaultFor {
	public $A;
	public $ARights;
	public $B;
	public $BRights;
	public $C;
	public $CRights;
	public $D;
	public $DRights;

	function __construct($A, $ARights, $B, $BRights, $C, $CRights, $D, $DRights) {
		$this->A 		= $A;
		$this->ARights 	= $ARights;
		$this->B 		= $B;	
		$this->BRights 	= $BRights;
		$this->C 		= $C;
		$this->CRights 	= $CRights;
		$this->D 		= $D;
		$this->DRights 	= $DRights;
	}
}


Class LogDefaultMeta {
	public $Meta1;
	public $Meta2;
	public $Meta3;
	public $Meta4;
	public $Meta5;

	function __construct($A=null, $B=null, $C=null, $D=null, $E=null) {
		$this->Meta1 = $A;
		$this->Meta2 = $B;
		$this->Meta3 = $C;
		$this->Meta4 = $D;
		$this->Meta5 = $E;
	}
}