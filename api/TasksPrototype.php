<?php
/**
*	contains class TasksPrototype
*	
*	@author Jeff Frohner
*	@copyright Copyright (c) 2015
*	@license   private
*	@version   0.8
*
**/



namespace Jeff\Api;

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
Class TasksPrototype 
{
	/** @var \MySqliDb Instance of database class */
	protected $db = NULL;
	/** @var object the file-config as defined in consuming app */
	protected $fileConfig;
	/** @var Environment Instance of Environment class */
	protected $ENV = NULL;
	/** @var ErrorHandler Instance of ErrorHandler class */
	protected $errorHandler = NULL;
	/** @var Models\Account instance of Log */
	protected $account = NULL;
	/** @var Log\Log instance of Log */
	protected $log = NULL;
	/** @var pseudo modelName, needed for db-definition and auto-db-Update only */
	public $modelName = "Task";
	/** @var string $dbTable The name of the corresponding database table */
	protected $dbTable = "tasks";
	/**
	*
	* Database-Table definition to be used by {@see DBHelper} class to create the corresponding table.
	* @see Models\Model for specs
	*/
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
			array ('requiredDate', 'timestamp', null, true, 'CURRENT_TIMESTAMP'),
			array ('resolvedDate', 'timestamp', null, true, NULL),
			array ('fulfilled', 'tinyint', '1', false, '0'),
			array ('rejected', 'tinyint', '1', false, '0'),
			array ('by', 'int', '11', true, NULL),
		);
	/** @var string $dbPrimaryKey primary database id/key. */	
	public $dbPrimaryKey = 'id';
	/**
	 * returns the db-tableName
	 * @return string name of the corresponding database table
	 */
	public function getDbTable() {
		return $this->dbTable;
	}

	/** 
	 * The Constructor.
	 * sets the passed classes/objects to local vars.
	 *
	 * @param  MySqliDb $db Instance of database
	 * @param Environment $ENV Instance of Environment
	 * @param ErrorHandler $errorHandler Instance of ErrorHandler
	 * @param Models\Account $account Instance of Models\Account
	 * @param Log\Log $log Instance of Log\Log
	 */
	public function __construct($db, $ENV, $errorHandler, $account=null, $log=null) {
		$this->db = $db;
		$this->ENV = $ENV;
		$this->errorHandler=$errorHandler;
		$this->account = $account;
		$this->log = $log;
	
		if(!$this->errorHandler) { $this->errorHandler = new ErrorHandler(); }
	}

	/**
	 * old deprecated version of authenticateAccount.
	 *
	 * __DEPRECATED__  - use authenticateAccount instead
	 * 
	 * @param  object $data the data object that was sent via POST:
	 * 
	 *     {
	 *         authToken: '1234567890'
	 *     }
	 *                      
	 * @return Models\Account       the authenticated Account
	 */
	protected function authorizeAccount($data) {
		echo "method authorizeAccount() was renamed to authenticateAccount().";
		exit;
	}

	/**
	 * authenticates an account by given dataset or http-headers.
	 * Throws errors on failure
	 * 
	 * @param  object $data the data object that was sent via POST:
	 * 
	 *     {
	 *         authToken: '1234567890'
	 *     }
	 *                      
	 * @return Models\Account       the authenticated Account
	 */
	protected function authenticateAccount($data) {
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


	/**
	 * will send the user to error page via header(location).
	 * Target url: 
	 * `"location: ".$this->ENV->urls->appUrl.'publicLinks/error?type='.$type.'&msg='.urlencode($errors[0]['msg']))`
	 * @param  array $errors must be an Error Array with an property "msg" in first Error-Item
	 * @param  string $type   [description]
	 * @return void         [description]
	 */
	protected function _gotoErrorPage($errors, $type='') {
		header("location: ".$this->ENV->urls->appUrl.'publicLinks/error?type='.$type.'&msg='.urlencode($errors[0]['msg']));
	}

	/**
	 * generates a random string of given length
	 * @param  int $length    how long the string shall be
	 * @return string         the random string
	 */
	protected function _getRandomString($length) {
		$template = "1234567890abcdefghijklmnopqrstuvwxyz";
		settype($rndstring, "string");
		for ($a = 0; $a <= $length; $a++) {
			   $b = rand(0, strlen($template) - 1);
			   $rndstring .= $template[$b];
		}
		return $rndstring;
	}

	/**
	 * gets a task by given task code.
	 * @param  string $code the randomString code
	 * @return object       object with the task and additional Info related to the task
	 * 
	 *     {
	 *         task: {taskobject}
	 *         data: {additionalData}
	 *     }
	 */
	public function getTaskByCode($code) {
		$this->db->where("code", $code);
		$task = $this->db->getOne($this->dbTable);
		$payload = new \stdClass();
		$payload->task = $task;
		$payload->data = $this->getAdditionalData($task);
		return $payload;
	}

	/**
	 * gets a task by given task id.
	 * @param  int $id the id
	 * @return object       object with the task
	 */
	public function getTaskById($id) {
		$this->db->where("id", $id);
		$task = $this->db->getOne($this->dbTable);
		return $task;
	}

	/** 
	 * prototype method to add additionalData.
	 * To be overridden in Task.php of consuming app
	 * @param array $task the task as stored in db
	 * @return  object a custom objectof any content
	 */
	public function getAdditionalData($task) {
		$data = new \stdClass();
		return $data;
	}
}
