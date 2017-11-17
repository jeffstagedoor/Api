<?php
/**
*	Class Account
*	
*	@author Jeff Frohner
*	@copyright Copyright (c) 2015
*	@license   private
*	@version   1.3
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
	const DEFAULT_PREFS = "{}";
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
			array ('lastOnline', 'timestamp', null, true),
			array ('lastLogin', 'timestamp', null, true),
			array ('invitationToken', 'varchar', '250', true),
			array ('invitedBy', 'int', '11', true),
			array ('modDate', 'timestamp', NULL, false, 'CURRENT_TIMESTAMP', 'on update CURRENT_TIMESTAMP'),
			array ('modBy', 'int', '11', true),

		);
	public $dbPrimaryKey = 'id';

	private $dbColsToFetch = array('id', 'email', 'fullName', 'firstName', 'middleName', 'prefixName', 'lastName', 'lastOnline', 'lastLogin');
	
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
			$log = new Api\LogLogin($this->db, $this->ENV, $this->errorHandler);
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
		// first check if the given identification is ok
		if(!isset($data->email) || !$this->checkIdentification($data->email)) {
			$e = Array("msg"=>'Identification/Email is not valid or already used', "code"=>1);
			array_push($this->errors,$e);
			return false;
		}

		// check if the given password is ok
		if(!isset($data->password) || !$this->isValidPassword($data->password)) {
			$e = Array("msg"=>'Password is not valid', "code"=>2);
			array_push($this->errors,$e);
			return false;
		}
		// check if passwordReapeat is ident
		if(!isset($data->passwordConfirm) || ($data->password != $data->passwordConfirm)) {
			$e = Array("msg"=>'Password is not ident with confirmation', "code"=>3);
			array_push($this->errors,$e);
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
			'prefs' => self::DEFAULT_PREFS,
			'signup' => $this->db->now()
		);
		$id = $this->db->insert($this->dbTable, $insertData);

		// if all went well, return the id of the new user back to api
		if($id) {
			return $id;	
		} else {
			$e = Array("msg"=>'database error', "code"=>9);
			array_push($this->errors,$e);
			return false;
		}
	}
	// END SIGNUP

	public function verifyCredentials($identification, $password) {
		$this->db->where('email', $identification);
		$cols = Array("id", "password", "authToken");
		$user = $this->db->getOne($this->dbTable, $cols);
		if($user) {
			$return = new \stdClass();
			$return->user = $user;
			// identification found, check password:
			if(password_verify($password, $user['password'])) {
				$return->success = true;
				return $return;
			} else {
				$return->success = false;
				return $return;
			}
		}
		return false;
	}

	/**
	* method LOGIN
	* used to login as a user
	* @param $identification, $password
	* @return obj with authToken and id of logedin user or false if an error occured
	*/
	public function login($identification, $password) {
		
		$return = new \stdClass();
		$verifyCredentials = $this->verifyCredentials($identification, $password);
		if($verifyCredentials->success) {
				$user = $verifyCredentials->user;
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
				require_once("Log.php");
				$log = new Api\LogLogin($this->db, $this->ENV, $this->errorHandler);
				$id = $log->writeLoginLog($user['id'],true,true);

		} else {
			// password wrong
			$this->errorHandler->add(91);
			// make new entry in logLogin
			require_once("Log.php");
			$user = isset($verifyCredentials->user) ? isset($verifyCredentials->user) : array("id"=>-1);
			$log = new Api\LogLogin($this->db, $this->ENV, $this->errorHandler);
			$id = $log->writeLoginLog($user['id'],true,false);
			return false;

		}
		return $return;
	}


	// authenticate = pseudo for LOGIN
	public function authenticate($identification, $password) {
		return $this->login($identification, $password);
	}



	/**
	* method changePassword
	* return the id of changedUser
	* @param $id, $password
	* @return int $id or false if an error occured
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
	* method mockAccount
	* mocks an account for developing
	* @param 
	* @return 
	*/
	public function mockAccount($id=1, $workgroups=Array()) 
	{
		$this->id = $id;
		$x = new \stdClass();
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
	

	/**
	* 	method getWorkgroups
	*	@param [account-id]
	*	@return (Array) of (Array) workgroups the user/account is connected to in format: workgroups[id] = rights;
	*/
	public function getWorkgroups() 
	{
		echo "DEPRECATED getWorkgroups - in Class Account - move to somewhere else. somehow.";
		// if (isset($this->data->workgroups)) {
		// 	return $this->data->workgroups;
		// }
		// if (func_num_args()>0) {
		// 	$args = func_get_args();
		// 	$id = $args[0];
		// } else {
		// 	$id = $this->id;
		// }

		// $cols = Array('workgroup','rights');
		// $this->db->where('user', $id);
		// $this->db->orderBy('workgroup', 'asc');
		// $u2ws = $this->db->get('user2workgroup', null, $cols);

		// $workgroups = Array();
		// if ($this->db->count>0) {
		// 	foreach ($u2ws as $u2w) {
		// 		$workgroups[$u2w['workgroup']] = $u2w['rights'];
		// 	}
		// }
		// return $workgroups;
	}


	/**
	* 	method getProductions
	*	@param none, gets it of the class itself, namly of this->id and this->data->workgroups
	*	@return (Array) of (Array) productions the user/account is connected to in format: productions[id] = rights;
	*/
	public function getProductions() 
	{
		echo "DEPRECATED getProductions - in Class Account - move to somewhere else. somehow.";
		// if (isset($this->data->productions)) {
		// 	return $this->data->productions;
		// }
		// $id = $this->id;
		// // fetch the productions the user is connected to (user2production), and add those of the workgroups he's connected to.
		// $cols = Array('production','rights');
		// $this->db->where('user', $id);
		// $this->db->orderBy('production', 'asc');
		// $u2ps = $this->db->get('user2production', null, $cols);
		// $productions = Array();
		// if($this->db->count>0) {
		// 	foreach ($u2ps as $u2p) {
		// 		$productions[$u2p['production']] = $u2p['rights'];
		// 	}
		// }
		// // add all productions from workgroups the user is member in (rights>=1)
		// $y = Array();
		// $wgs = $this->getWorkgroups();
		// foreach ($wgs as $id => $rights) {
		// 	if($rights>0) {
		// 		$y[] = $id;
		// 	}
		// }
		// $cols = Array('id');
		// $this->db->where('workgroup', $y, 'IN');

		// if(count($productions)) {
		// 	$this->db->where('id', array_keys($productions), 'NOT IN');	// exclude those just added above!
		// }
		// $this->db->orderBy('id', 'asc');
		// $ps = $this->db->get('productions', null, $cols);
		// if($this->db->count>0) {
		// 	foreach ($ps as $pItem) {
		// 		$productions[$pItem['id']] = null;
		// 	}
		// }
		// $this->data->productions = $productions;
		// return $productions;
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

?>