<?php
/**
*	Class Account
*	
*	@author Jeff Frohner
*	@copyright Copyright (c) 2015
*	@license   private
*	@version   1.4
*
**/

namespace Jeff\Api\Models;
use Jeff\Api as Api;

require_once('Model.php');
require_once($ENV->dirs->appRoot.'Constants.php');


Class Account extends Model
{
	// this is a very specific class to retrieve and set informations about the authorized user.
	public $modelName = 'account';
	public $modelNamePlural = 'accounts';
	public $id = null;
	public $data = null;
	public $isAuthenticated = false;
	protected $db = null;
	protected $dbIdField = 'id';
	protected $dbTable = 'accounts';


	// defaults
	const DEFAULT_RIGHTS = 1;
	const DEFAULT_STTINGS = "{}";
	const AUTHCODE_LENGTH = 60;
	const SEARCH_MIN_LENGTH = 6;

	// additional model-specific requirements
	protected $identification = "email";
	protected $identificationIsEmail = true;
	protected $minPasswordLength = 8;
	protected $minIdentificationLength = 6; // 6 is minimum for email: a@b.cd

	public $dbDefinition = Array(
			array ('id', 'int', '11', false, NULL, 'auto_increment'),
			array ('email', 'varchar', '80', false),
			array ('password', 'varchar', '250', true),
			array ('rights', 'tinyint', '4', false, '0'),
			array ('authToken', 'varchar', '250', true),
			array ('refreshToken', 'varchar', '250', true),
			array ('fullName', 'varchar', '80', false, ''),
			array ('firstName', 'varchar', '20', false, ''),
			array ('middleName', 'varchar', '20', false, ''),
			array ('prefixName', 'varchar', '20', false, ''),
			array ('lastName', 'varchar', '30', false, ''),
			array ('profilePic', 'varchar', '100', false, ''),
			array ('lastOnline', 'timestamp', null, true),
			array ('lastLogin', 'timestamp', null, true),
			array ('invitationToken', 'varchar', '250', true),
			array ('invitedBy', 'int', '11', true),
			array ('modDate', 'timestamp', NULL, false, 'CURRENT_TIMESTAMP', 'on update CURRENT_TIMESTAMP'),
			array ('modBy', 'int', '11', true),

		);
	public $dbPrimaryKey = 'id';

	private $dbColsToFetch = array('id', 'email', 'fullName', 'firstName', 'middleName', 'prefixName', 'lastName', 'lastOnline', 'lastLogin');
	

	protected $doNotUpdateFields = ['email','password','authToken', 'invitationToken', 'lastOnline', 'lastLogin', 'signin'];

	// Sideloads & References (hasMany-Fields)
	// public $hasManyFields = Array (
	// 		Array("name"=>'user2workgroups',"dbTable"=>'user2workgroup', "dbTargetFieldName"=>'workgroup', "dbSourceFieldName"=>'user', "saveToStoreField"=>'workgroup', "saveToStoreName"=>'workgroups'),
	// 		Array("name"=>'user2productions',"dbTable"=>'user2production', "dbTargetFieldName"=>'production', "dbSourceFieldName"=>'user', "saveToStoreField"=>'production', "saveToStoreName"=>'productions'),
	// 	);

	// public $sideloadItems = Array(
	// 		Array("name"=>'user2workgroups',"dbTable"=>'user2workgroup', "reference"=>'user2workgroups'),
	// 		Array("name"=>'workgroups', "dbTable"=>'workgroups', "reference"=>'workgroups', "class"=>'Workgroups'),
	// 		Array("name"=>'user2productions', "dbTable"=>'user2production', "reference"=>'user2productions'),
	// 		Array("name"=>'productions', "dbTable"=>'productions', "reference"=>'productions', "class"=>'Productions'),
	// 	);

	public $specialMethods = Array("changeName");




	/**
	* method reAuthenticate
	* reauthenticate a user by sent authToken
	* @param $authToken
	* @return true or false if reAuth failed
	*/
	public function reAuthenticate($authToken) 
	{
		$this->db->where('authToken', $authToken);
		$user = $this->db->ObjectBuilder()->getOne($this->dbTable, null, $this->dbColsToFetch);
		if($user) {
			$this->isAuthenticated = true;
			$this->id = $user->id;
			$this->data = $this->buildAccountObject($user);
			// DISABLED:
			// write LoginLog
			// require_once("Log/LogLogin.php");
			// $log = new Api\Log\LogLogin($this->db, $this->ENV, $this->errorHandler);
			// $id = $log->writeLoginLog($user->id,'reAuth',false,true);
			// BECAUSE: would write a log for each api-call.
			return true;
		} else {
			return false;
		}
	}

	/**
	* method reAuthenticateByInvitationToken
	* reauthenticate a user by sent invitationToken
	* @param $invitationToken
	* @return true or false if reAuth failed
	*/
	public function reAuthenticateByInvitationToken($invitationToken) 
	{
		$this->db->where('invitationToken', $invitationToken);
		$user = $this->db->ObjectBuilder()->getOne($this->dbTable, null, $this->dbColsToFetch);
		if($user) {
			// make new entry in logLogin
			require_once("Log/LogLogin.php");
			$log = new Api\Log\LogLogin($this->db, $this->ENV, $this->errorHandler);
			$id = $log->writeLoginLog($user->id,'reAuthByInvitation',false,true);

			$this->isAuthenticated = true;
			$this->id = $user->id;
			$this->data = $this->buildAccountObject($user);
			return true;
		} else {
			return false;
		}
	}

	/**
	* method refreshToken
	* refresh the authToken for a user, identified by sent refreshToken
	* @param $refreshToken
	* @return obj $user or false if reAuth failed
	*/
	public function refreshToken($refreshToken) 
	{
		$this->db->where('refreshToken', $refreshToken);
		$user = $this->db->ObjectBuilder()->getOne($this->dbTable, null, $this->dbColsToFetch);
		if($user) {
			// make a new authToken, save it to db and send it back to client
			$return = new \stdClass();
			$return->account_id = $user->id;
			$return->authToken = $this->makeAuthCode();
			$return->refreshToken = $this->makeAuthCode();

			$data = Array (
				'lastOnline' => $this->db->now(),
				'refreshToken' => $return->refreshToken,
				'authToken' => $return->authToken
			);
			$this->db->where('id', $user->id);
			$this->db->update($this->dbTable, $data);
			// make new entry in logLogin
			require_once("Log.php");
			$log = new Api\Log\LogLogin($this->db, $this->ENV, $this->errorHandler);
			$id = $log->writeLoginLog($user->id,true,true);
			
			$this->isAuthenticated = true;
			$this->id = $user->id;
			$this->data = $this->buildAccountObject($user);
			return $return;
		} else {
			return false;
		}
	}



	/**
	* method getAuthById
	* 
	* @param $id of user/account
	* @return the user's authToken
	*/
	public function getAuthById($id) {
		$this->db->where('id',$id);
		$item = $this->db->getOne($this->dbTable, null, Array('authToken'));
		return $item['authToken'];
	}


	/**
	* method SIGNUP
	* used to sign in a new user
	* @param stdClass with at least
	* 	$identification
	*	$password
	* @return id of newly created user or false if an error occured
	*/
	public function signup($data) {
		// echo "bin in signup mit data:\n";
		// var_dump($data);

		// first check if the given identification is ok
		if(!isset($data->email) || !$this->checkIdentification($data->email)) {
			$this->errorHandler->add(array("Signup Error", "Identification/Email is not valid or already taken.", 409, Api\ErrorHandler::CRITICAL_EMAIL, false));
			return false;
		}

		// check if the given password is ok
		if(!isset($data->password) || !$this->isValidPassword($data->password)) {
			$this->errorHandler->add(array("Signup Error", "The given password is not valid", 409, Api\ErrorHandler::CRITICAL_EMAIL, false));
			return false;
		}
		// check if passwordReapeat is ident
		if(!isset($data->passwordConfirm) || ($data->password != $data->passwordConfirm)) {
			$this->errorHandler->add(array("Signup Error", "The given passwords do not match.", 409, Api\ErrorHandler::CRITICAL_EMAIL, false));

			return false;
		}

		// get a freshly made AuthToken:
		$auth = $this->makeAuthCode();

		// write new user to database
		$insertData = Array(
			'email' => $data->email,
			'password' => password_hash($data->password, PASSWORD_DEFAULT),
			'authToken' => $auth,
			'firstName' => ucfirst(strtolower(trim($data->firstName))),
			'middleName' => ucfirst(strtolower(trim($data->middleName))),
			'prefixName' => trim($data->prefixName), // leave prefix as is
			'lastName' => ucfirst(strtolower(trim($data->lastName))),
			'fullName' => trim($data->fullName),
			'rights' => self::DEFAULT_RIGHTS,
			'settings' => self::DEFAULT_STTINGS,
			'signup' => $this->db->now()
		);
		$id = $this->db->insert($this->dbTable, $insertData);

		// if all went well, generate the account, send 'true' back to api
		if($id) {
			// make new entry in logLogin
			require_once("Log/LogLogin.php");
			$log = new Api\Log\LogLogin($this->db, $this->ENV, $this->errorHandler);
			$id = $log->writeLoginLog($user['id'],'signup',true,true);
			// get new account
			$this->db->where('id', $id);
			$this->data = $this->db->ObjectBuilder()->getOne($this->dbTable);
			return true;
		} else {
			return false;
		}
	}

	/**
	* method veryfyCredentials
	* checks wheater given credentials are correct/correstpondend to those save in db
	* @param $identification, $password
	* @return obj with user or false if an error occured
	*/
	public function verifyCredentials($identification, $password) {
		$this->db->where('email', $identification);
		$cols = Array("id", "password", "authToken");
		$user = $this->db->getOne($this->dbTable, $cols);
		if($user) {
			$return = new \stdClass();
			// identification found, check password:
			if(password_verify($password, $user['password'])) {
				unset($user['authToken']);
				$return->user = $user;
				$return->success = true;
				return $return;
			} else {
				unset($user['password']);
				unset($user['authToken']);
				$return->user = $user;
				$return->success = false;
				return $return;
			}
		}
		return false;
	}


	/**
	* method comparePasswords
	* compare a given password with the one saved for the current user
	* @param $password (needs set id in this class)
	* @return true if they match, false if not
	*/
	public function comparePasswords($password) {
		$this->db->where('id', $this->id);
		$cols = Array("id", "password");
		$user = $this->db->getOne($this->dbTable, $cols);
		if($user) {
			return password_verify($password, $user['password']);
		}
		$this->errorHandler->throwOne("API Error", "Accounts::_comparePasswords didn't find the demanded user", 500, ErrorHandler::CRITICAL_EMAIL, true);
		exit;
		// return false;
	}

	/**
	* method LOGIN
	* used to login as a user
	* @param $identification, $password
	* @return obj with authToken and id of logedin user or false if an error occured
	*/
	public function login($identification, $password) {
		
		$return = new \stdClass();
		$verify = $this->verifyCredentials($identification, $password);
		if($verify && $verify->success) {
				$user = $verify->user;
				// update login-timestamp and maybe authToken in db
				$data = Array (
					'lastLogin' => $this->db->now(),
					'lastOnline' => $this->db->now()
				);


				$return->authToken = $this->makeAuthCode();
				$data['authToken'] = $return->authToken;
				$return->refreshToken = $this->makeAuthCode();
				$data['refreshToken'] = $return->refreshToken;

				$return->account_id = $user['id'];
				$this->db->where('id', $user['id']);
				$this->db->update($this->dbTable, $data);
				// make new entry in logLogin
				require_once("Log/LogLogin.php");
				$log = new Api\Log\LogLogin($this->db, $this->ENV, $this->errorHandler);
				$id = $log->writeLoginLog($user['id'],'login',true,true);

		} else {
			// password wrong
			$this->errorHandler->add(91);
			// make new entry in logLogin
			require_once("Log/LogLogin.php");
			$user = isset($verify->user) ? $verify->user : array("id"=>-1);
			$log = new Api\Log\LogLogin($this->db, $this->ENV, $this->errorHandler);
			$id = $log->writeLoginLog($user['id'],'login', true,false);
			return false;

		}
		return $return;
	}


	// authenticate = pseudo for LOGIN
	public function authenticate($identification, $password) {
		return $this->login($identification, $password);
	}



	/**
	* Sets and saves new password to database
	*
	* The password is hashed via php's native password_hash() without special settings
	* The saved authToken will be set to ''. Account needs to relogin to get a new one.
	* This should be done in client-app automaticly
	*
	* @param int $id the account's id
	* @param string $password the new password
	* @return int $id or false if an error occured
	*
	*/
	public function changePassword($id, $password) {
		$return = new \stdClass();
		if($id<1) return false;	// check if we have a valid id
		$this->db->where('id',$id);
		$data = Array (
			'password' => password_hash($password, PASSWORD_DEFAULT),
			// unset authToken in db, will be added on auto-re-login
			'authToken' => ''
		);
		$row = $this->db->update($this->dbTable, $data);
		if($row) {
			return $row['id'];
		} else {
			return false;
		}
	}

	/**
	* Sets and saves the new name to database
	*
	* @param int $id 
	* @param string $password
	* @return int $id or false if an error occured
	*/
	public function changeName($data, $account, $request) {
		if(isset($data->account) && $data->account != $account->id) {
			/* then a user wants to set someone elses Name.
			 * so we check if the current user is allowed to do that.
			 */
			if($account->data->rights < \Constants::USER_ADMIN) {
				$this->errorHandler->throwOne(array("Not allowed", "You are not allowed to change someone elses name.",400, ErrorHandler::CRITICAL_LOG, false));
				exit;
			} else {
				$id = $data->account;
			}
		} else {
			$id = $account->id;
		}
		$return = new \stdClass();
		require_once($this->ENV->dirs->api."Names.php");
		$data = Api\Names::createNameSet($data);
		$dbData = (array) $data;


		$this->db->where('id',$id);
		$success = $this->db->update($this->dbTable, $dbData);
		if($success) {
			$logData = new \stdClass();
			$logData->for = new Api\Log\LogDefaultFor($id,\Constants::USER_ADMIN,NULL,NULL,NULL,NULL,NULL,NULL);
			$logData->meta = new Api\Log\LogDefaultMeta(NULL,NULL, NULL, $data->fullName, json_encode($data));
			$log = new \stdClass();
			$log->account = $this->account->id;
			$log->type= "changeName";
			$log->item = "account";
			$log->data = $logData;

			$return->log = $log;
			$return->data = $data;
			$return->id = $id;
			$return->success = true;
			return $return;
		} else {
			$return->success = false;
			$return->error = 'Error while trying to changeName(). Please see logs.';
			$dbError = $this->db->getLastError();
			$this->errorHandler->throwOne(array("DB-Error", "Error while trying to changeName() in ".__FILE__." with DB-Error: ".$dbError,500, ErrorHandler::CRITICAL_LOG, true));
			return $return;
		}
	}


	/**
	* method mockAccount
	* mocks an account for developing
	* @param 
	* @return 
	*/
	public function mockAccount($id=1, $workgroups=Array()) 
	{
		$this->id = $id;
		$x = new \stdClass();
		$x->id = 0;
		$x->rights = 9;
		$x->workgroups = $workgroups;
		$this->data = $x;
	}

	/**
	* method updateLastOnline
	* @param
	* @return true/false on success/fail
	*/
	public function updateLastOnline($id=null) 
	{
		if(is_null($id)) {
			$id = $this->id;
		}
		$data = Array (
			'lastOnline' => $this->db->now()
		);
		$this->db->where('id', $id);
		$this->db->update($this->dbTable, $data);
		if($this->db->count) { 
			return true;
		} else {
			return false;
		}

	}



	/**
	*	buildAccountObject
	*	returns an Object with Account-Information with following structure:
	*
	*	{
	*		id: 1,
	*		identification: maxmustermann@gmail.com,
	*		personalDetails: {
	*			fullName: "Jeff Frohner",
	*			....
	*		},
	*		rights: 1-5, 
	*		// workgroups: [1=>3, 2=>0, 3=>4, ...], // id=>rights
	*		
	*		// productions: [3=>0, ...], // not anymore added automaticly, shall be retreived only when needed in Model-Hook 'beforeGetAll()'
	*		...
	*		
	*
	*	}
	*/
	private function buildAccountObject($user) 
	{
		$a = new \stdClass();
		$a->id = $user->id;
		$a->identification = $user->email;
		$personalDetails = new \stdClass();
		$personalDetails->fullName = isset($user->fullName) ? $user->fullName : null;
		$a->personalDetails = $personalDetails;

		$a->rights = isset($user->rights) ? $user->rights : null;
		return $a;
	}


	/**
	* 	method getAccount
	*	@param none
	*	@return previously set Account-Data
	*/

	public function getAccount() 
	{
		return $this->data;
	}
	

	/*
	*	Checkers & Helpers
	*
	*
	*/


	// check if given identification is in correct format
	public function checkIdentification($identification) {
		if(strlen($identification)<$this->minIdentificationLength) {
			return false;
		}

		if(preg_match('/[\s\'";,]/', $identification)) return false;

		if($this->identificationIsTaken($identification)) {
			return false;
		}
		if($this->identificationIsEmail && !$this->isValidEmail($identification)) {
			return false;
		}
		return true;
	}

	// check if given identification is already taken in db
	private function identificationIsTaken($identification) {
		$this->db->where($this->identification, $identification);
		$dbresult = $this->db->getOne($this->dbTable);
		if($dbresult) return true;
		return false;

	}

	// check if given string is a valid email
	private function isValidEmail($email) {
		return filter_var($email, FILTER_VALIDATE_EMAIL);
	}

	public function isValidPassword($password) {
		if(strlen($password)<$this->minPasswordLength) return false;
		// check for forbidden characters (space, tab fe)
		if(preg_match('/[\s\'";,.]/', $password)) return false;
		return true;
	}

	private static function makeAuthCode() {
		return self::GetRandomString(self::AUTHCODE_LENGTH);
	}

	private static function GetRandomString($length=250) {
		$template = '1234567890abcdefghijklmnopqrstuvwxyz';
		$rndstring='';
		for ($a = 0; $a <= $length; $a++) {
			   $b = rand(0, strlen($template) - 1);
			   $rndstring .= $template[$b];
		}
		return $rndstring;
	}

}