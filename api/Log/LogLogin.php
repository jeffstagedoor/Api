<?php
/**
*	Class LogLogin
*	
*	@author Jeff Frohner
*	@copyright Copyright (c) 2015
*	@license   private
*	@version   1.0.0
*
**/
namespace Jeff\Api\Log;
use Jeff\Api as Api;
use Jeff\Api\ErrorHandler;
use Jeff\Api\Error;

require_once('Log.php');

Class LogLogin extends Log {
	public $modelName = "LogLogin";

	// private $dbTable = \Jeff\LogConfig::DB_TABLE_LOGIN;
	protected $dbTable = "loglogin";
	public $dbDefinition = Array(
			array ('id', 'int', '11', false, NULL, 'auto_increment'),
			array ('user', 'int', '11', false),
			array ('authType', 'varchar', '20', true),
			array ('loginattempt', 'tinyint', '1', false),
			array ('success', 'tinyint', '1', false),
			array ('timestamp', 'timestamp', null, false, 'CURRENT_TIMESTAMP', 'ON UPDATE CURRENT_TIMESTAMP'),
			
			array ('referer', 'varchar', '150', false),
			array ('userAgent', 'varchar', '150', false),
			array ('userAgentOs', 'varchar', '30', false),
			array ('userAgentBrowser', 'varchar', '50', false),
			array ('ip4', 'varchar', '15', false),
			array ('ip6', 'varchar', '39', false),

			array ('long', 'int', '11', true),
			array ('lat', 'int', '11', true),
			
			array ('geoCity', 'varchar', '50', false),
			array ('geoRegion', 'varchar', '50', false),
			array ('geoCountry', 'varchar', '10', false),
			array ('geoOrg', 'varchar', '50', false),
			array ('geoPostal', 'varchar', '15', false),
		);
	public $dbPrimaryKey = 'id';

	public static function writeLoginLog($user, $type, $loginattempt, $success) {
		self::$user = $user;
		self::$loginattempt = $loginattempt;
		self::$success = $success;
		self::$type = $type;
		$result = self::$db->rawQuery("SHOW FULL TABLES LIKE '".\LogConfig::$dbTableLogin."'");
		if(count($result)>0) {
			// debug('writeLoginLog insert');
			try {
				$id = self::$db->insert(\LogConfig::$dbTableLogin, self::collectData());
			} 
			catch (\Exception $e) {
				ErrorHandler::add(new Error(ErrorHandler::DB_ERROR));
				ErrorHandler::add(new Error(array('DB-LogLogin',"db Error: \n".self::$db->getLastError()."\non query:\n".self::$db->getLastQuery()."\nin File ".__FILE__.":".__LINE__." - ".get_class(), 500, ErrorHandler::CRITICAL_ALL, true, $e)));

				$id=NULL;
			}
			// debug(self::$db->getLastQuery(), __FILE__, __LINE__, get_class());
			// debug(self::$db->getLastError(), __FILE__, __LINE__, get_class());
			return $id;
		} else {
			ErrorHandler::add(new Error(ErrorHandler::LOG_NO_TABLE_LOGIN));
			ErrorHandler::sendErrors();
		}
	}
}