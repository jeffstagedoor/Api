<?php

namespace Jeff\myStagedoor;


Class Rights extends SplEnum{
	Account = new stdClass();
	Account->DISABLED = 0;
	Account->USER = 1;
	Account->ADMIN = 2;
	Account->SUPERADMIN = 3;
	Workgroup = new stdClass();
	Workgroup->CONTESTENT = 0;
	Workgroup->MEMBER = 1;
	Workgroup->ADMIN = 2;
	Production = new stdClass();
	Production->ARTIST = 1;
	Production->HOD_TECH = 3;
	Production->HOD_CREATIVE = 4;
	Production->STAGEMANAGEMENT = 5;
	Production->COMPANYMANAGEMENT = 7;
	Production->ADMIN = 9;
}


Class Authorizor {

	private $db = NULL;
	private $account = stdClass();

	/*
	*	Constructor
	*	@params: $account is an object of account-class containing the requesting user's account information, including his rights
	*	gets a database object passed into
	*
	*/
	function __construct($account, $db=NULL) {
		$this->account = $account;
		if($db) {
			$this->db = $db;
		} else {
			require_once('../config.php');
			require_once('../classes/libs/MysqliDb.php');
			$this->db = new MysqliDb($ENV->database);
		}
	}

	/**
	* the actual authorization-method
	*	
	* checks if the user is allowed to acces/modify specified information according to his given rights,
	* which are taken from $account-object
	*	Structure of that account-object: (more updated version will be found in class Account.php)
	*
	*		{
	*			id: 1,
	*			identification: maxmustermann@gmail.com,
	*			personalDetails: {
	*				fullName: "Jeff Frohner",
	*				....
	*			},
	*			rights: {
	*				account: 1-5,
	*				workgroups: [
	*					{id: 1, rights: 3},
	*					{id: 2, rights: 0}
	*				],
	*				productions: [
	*					{id: 1, rights: 1},
	*					{id: 2, rights: 2},
	*				],
	*				auditions: [
	*					{id: 2, rights: 5}
	*				]
	*			}
	*		}
	*
	*
	* @param  $for is the name of the item the user wants to acces, for example a "production", a "workgroup", an "artist", ...
	*			$id the id of this item
	*			$type describes what he wants to do: crud (create, read, update, delete)
	* @return true if authorized, false if not...., 2-5 if a higher level is requested and granted! (is that a good idea?)
	**/
	public function authorize($for, $id, $type, $level=1) {
		$accountRights = $account->rights->account;

		switch $for {
			case "workgroup":
				
				$wgRights = $this->getRights("workgroups", $id);
				$wgprodrights = $this->getHighestProdRightsInWG($id);
				switch $type {
					case "c":

						if($accountRights < Rights->Account->USER) return false;
						return true;
					case "r":
						# first check account-admins
						if($accountRights >= Rights->Account->ADMIN) return true;
						# now check if user is member of workgroup
						if($wgRights >= Rights->Workgroup->MEMBER) return true;
						return false;
					case "u":
						# first check account-admins
						if($accountRights >= Rights->Account->ADMIN) return true;
						# now check if user is admin of workgroup
						if($wgRights >= Rights->Workgroup->ADMIN) return true;
						return false;
					case "d":
						# first check account-admins
						if($accountRights == Rights->Account->ADMIN) return true;
						# now check if user is admin of workgroup
						if($wgRights >= Rights->Workgroup->ADMIN) return true;
						return false;
					case "memberaccept":
						# first check account-admins
						if($accountRights == Rights->Account->SUPER_ADMIN) return true;
						if($wgRights >= Rights->Workgroup->ADMIN) return true;

				}


				break;
		}

		return false;
	}


	/*
	* Helper-function that finds the object with the given id and returns matching value of "rights"
	* returns 0 if no matching entry found
	*/
	private function getRights($for, $id) {
		foreach ($this->account->rights->{$for} as $key => $obj) {
			if($obj['id']==$id) return $obj['rights'];
		}
		return 0;
	}

	/*
	*
	*/
	private function getHighestProdRightsInWG($wgid) {
		throw new Exception("Error: getHighestProdRightsInWG in Class Authorizor not yet implemented", 1);
		
	}
}

?>