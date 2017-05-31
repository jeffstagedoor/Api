<?php
/**
*	Class Err
*	
*	
*	@author Jeff Frohner
*	@copyright Copyright (c) 2015
*	@license   private
*	@version   1.0
*
**/

namespace Jeff\Api;

Class Err {
	
	Const DB_ERROR = 					20;
	Const DB_NOT_FOUND = 				21;

	Const MODEL_NOT_DEFINED = 			30;
	Const MODEL_NOT_ALLOWED = 			31;
	Const MODEL_NOT_SORTABLE = 			33;
	Const MODEL_SORT_OOR = 				34;


	Const API_INVALID_REQUEST = 		40;
	Const API_INVALID_POST_REQUEST = 	41;
	Const API_INVALID_GET_REQUEST = 	42;
	Const API_INVALID_PUT_REQUEST = 	43;


	Const AUTH_NO_AUTHTOKEN =		 	90;
	Const AUTH_FAILED =				 	91;
	Const AUTH_PWD_INCORRECT =		 	92;
	Const AUTH_USER_UNKNOWN =		 	93;

	Const AUTH_PWD_NOT_MATCHING =		97;
	Const AUTH_INT_ACCOUNTNOTSET =		99;


	private $Errs = Array();
	private $Codes = Array(

	// DB
		20 => Array("code"=>20, "title"=>"Database Error", "msg"=>"An undefined database error occured."),
		21 => Array("code"=>21, "title"=>"Database Error", "msg"=>"Could not find requested recourse."),
		22 => Array("code"=>22, "title"=>"Database Error", "msg"=>"Could not insert record."),

	// MODELS
		30 => Array("code"=>30, "title"=>"Model Error", "msg"=>"This Model is not defined"),
		33 => Array("code"=>33, "title"=>"Model Error", "msg"=>"Trying to sort an item, \nthat is not defined as sortable."),
		34 => Array("code"=>34, "title"=>"Model Error", "msg"=>"Trying to sort an item, \nbut that item is already first/last of group."),

	// API
		40 => Array("code"=>40, "title"=>"Invalid API request", "msg"=>"Invalid API request"),
		41 => Array("code"=>41, "title"=>"Invalid post request", "msg"=>"Invalid post request"),
		42 => Array("code"=>42, "title"=>"Invalid get request", "msg"=>"Invalid get request"),
		43 => Array("code"=>43, "title"=>"Invalid get request", "msg"=>"Invalid put request"),

	// Authorization
		90 => Array("code"=>90, "title"=>"No AuthToken found", "msg"=>"Could not find a valid authorization token."),
		91 => Array("code"=>91, "title"=>"Authentication failed", "msg"=>"Could not authenticate user."),
		92 => Array("code"=>92, "title"=>"Incorrect Password", "msg"=>"Password is not correct."),
		93 => Array("code"=>93, "title"=>"Unknown User", "msg"=>"Could not find a user with these credentials."),

		97 => Array("code"=>97, "title"=>"Not matching Passwords", "msg"=>"The passwords do not match."),

		99 => Array("code"=>99, "title"=>"Internal Error", "msg"=>"Account not set"),
	);


	public function __construct() {

	}


	public function add($e) {
		// if I get an Integer, it's the number-code of a predefined error
		if(is_integer($e)) {
			$this->Errs[] = $e;
		} else {
			throw new Exception("Error in Class Err: $e is not an Integer", 1);
		}
		// return all saved errors by defult, as a shortcut
		return $this->get();
	}

	// returns ALL saved Errors
	public function get() {
		$arr = Array();
		foreach ($this->Errs as $key => $code) {
			$arr[] = $this->Codes[$code];
		}
		return $arr;
	}

	public function hasErrors() {
		if(sizeof($this->Errs)>0) {
			return true;
		} else {
			return false;
		}
	}

	public function sendApiErrors() {
		$errors = $this->get();
		http_response_code(400);
		header("Content-Type: application/json");
		echo '{"errors": '.json_encode($errors). '}';
	}


}

?>