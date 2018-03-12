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

	public function writeLoginLog($user, $type, $loginattempt, $success) {
		$this->user = $user;
		$this->loginattempt = $loginattempt;
		$this->success = $success;
		$this->type = $type;
		$result = $this->db->rawQuery("SHOW FULL TABLES LIKE '".\LogConfig::DB_TABLE_LOGIN."'");
		if(count($result)>0) {
			// debug('writeLoginLog insert');
			try {
				$id = $this->db->insert(\LogConfig::DB_TABLE_LOGIN, $this->collectData());
			} 
			catch (\Exception $e) {
				$this->errorHandler->add(new Api\Error(Api\ErrorHandler::DB_ERROR));
				$this->errorHandler->add(new Api\Error(array('DB-LogLogin',"db Error: \n".$this->db->getLastError()."\non query:\n".$this->db->getLastQuery()."\nin File ".__FILE__.":".__LINE__." - ".get_class(), 500, Api\ErrorHandler::CRITICAL_ALL, true, $e)));

				$id=NULL;
			}
			// debug($this->db->getLastQuery(), __FILE__, __LINE__, get_class());
			// debug($this->db->getLastError(), __FILE__, __LINE__, get_class());
			return $id;
		} else {
			$this->errorHandler->add(new Error(ErrorHandler::LOG_NO_TABLE_LOGIN));
			$this->errorHandler->sendErrors();
		}
	}
}