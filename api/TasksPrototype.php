<?php
/**
*	Class TasksPrototype
*	
*	to be extended by consuming app as "Tasks"
*
*	@author Jeff Frohner
*	@copyright Copyright (c) 2015
*	@license   private
*	@version   0.8
*
**/



namespace Jeff\Api;

Class TasksPrototype 
{
	protected $db = NULL;
	protected $fileConfig;
	protected $ENV = NULL;
	protected $errorHandler = NULL;
	protected $account = NULL;
	protected $log = NULL;
	public $modelName = "Task";

	protected $dbTable = "tasks";
	public $dbDefinition = Array(
			array ('id', 'int', '11', false, NULL, 'auto_increment'),
			array ('forAccount', 'int', '11', true, NULL),
			array ('forAccountRights', 'int', '11', true, NULL),
			array ('forB', 'int', '11', true, NULL),
			array ('forBRights', 'int', '11', true, NULL),
			array ('forC', 'int', '11', true, NULL),
			array ('forCRights', 'int', '11', true, NULL),
			array ('type', 'varchar', '80', true, NULL),
			array ('code', 'varchar', '100', true, NULL),
			array ('meta1', 'int', '11', true, NULL),
			array ('meta2', 'int', '11', true, NULL),
			array ('meta3', 'int', '11', true, NULL),
			array ('message', 'varchar', '200', true, NULL),
			array ('requiredDate', 'timestamp', null, false, 'CURRENT_TIMESTAMP'),
			array ('resolvedDate', 'timestamp', null, true, NULL),
			array ('fulfilled', 'tinyint', '1', false, '0'),
			array ('rejected', 'tinyint', '1', false, '0'),
			array ('by', 'int', '11', true, NULL),
		);
	public $dbPrimaryKey = 'id';
	public function getDbTable() {
		return $this->dbTable;
	}

	/** CONSTRUCTOR
	*   just get the passed classes right.
	*
	*/
	public function __construct($db, $ENV, $errorHandler, $account=null, $log=null) {
		$this->db = $db;
		$this->ENV = $ENV;
		$this->errorHandler=$errorHandler;
		$this->account = $account;
		$this->log = $log;
	
		if(!$this->errorHandler) { $this->errorHandler = new ErrorHandler(); }
	}

	protected function authorizeAccount($data) {
		// check if and where we got an authToken
		$headers = getallheaders();
		if(isset($headers['Authorization'])) {
			$auth = explode(" ", $headers['Authorization']);
			$authToken = $auth[1];
			$authType = $auth[0];
		} elseif (isset($data->authToken)) {

			$authToken = $data->authToken;
		} else {	// no authtoken found -> send error & exit script!
			$this->errorHandler->throwOne(ErrorHandler::AUTH_NO_AUTHTOKEN);
			exit;
		}
		$success = $this->account->reAuthenticate($authToken);

		if(!$this->account->isAuthenticated) {	
			// authorization failed
			$this->errorHandler->throwOne(ErrorHandler::AUTH_FAILED);
			exit;
		} else {
			return $this->account->getAccount();
		}
	}

	protected function _gotoErrorPage($errors, $type='') {
		// will send the user to error page.
		// $errors must be an Error Array with an property "msg" in first Error-Item
		header("location: ".$this->ENV->urls->appUrl.'publicLinks/error?type='.$type.'&msg='.urlencode($errors[0]['msg']));
	}


	protected function _getRandomString($length) {
		$template = "1234567890abcdefghijklmnopqrstuvwxyz";
		settype($rndstring, "string");
		for ($a = 0; $a <= $length; $a++) {
			   $b = rand(0, strlen($template) - 1);
			   $rndstring .= $template[$b];
		}
		return $rndstring;
	}

	public function getTaskByCode($code) {
		$this->db->where("code", $code);
		$task = $this->db->getOne($this->dbTable);
		$payload = new \stdClass();
		$payload->task = $task;
		$payload->data = $this->getAdditionalData($task);
		return $payload;
	}

	public function getTaskById($id) {
		$this->db->where("id", $id);
		$task = $this->db->getOne($this->dbTable);
		return $task;
	}

	// to be overridden in Task.php Class Task
	public function getAdditionalData($task) {
		$data = new \stdClass();
		return $data;
	}
}
