<?php
/**
*	Class DataMasker
*	
*	Helper-Class, that privides methods to mask sensible data such as email, telefone numbers, ...
*	@author Jeff Frohner
*	@copyright Copyright (c) 2015
*	@license   private
*	@version   1.0
*
**/
namespace Jeff\Api\Utils;


Class DataMasker {
	const MASK_TYPE_EMAIL = "email";
	const MASK_TYPE_TEL = "tel";
	const MASK_TYPE_PWD = "pwd";
	const MASK_TYPE_NAME = "name";


	const EMAIL_SHOW_LEFT = 2;
	const EMAIL_SHOW_RIGHT = 4;
	const TEL_SHOW_LEFT = 4;
	const TEL_SHOW_RIGHT = 2;

	const EMAIL_REPLACE = "*";
	const TEL_REPLACE = "*";
	const PWD_REPLACE = "*";


	public static function mask($val, $type) {
		switch ($type) {
			case self::MASK_TYPE_EMAIL: 
				return self::maskEmail($val);
			case self::MASK_TYPE_TEL:
				return self::maskTel($val);
			case self::MASK_TYPE_PWD:
				return self::maskPwd($val);
			default:
				return self::maskAll($val);
		}
	}	

	private static function maskEmail($email) {
		if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$parts = explode("@", $email);
			// mask part 1:
			$left = substr($parts[0], 0,self::EMAIL_SHOW_LEFT) . self::makeAsterix(strlen($parts[0])-self::EMAIL_SHOW_LEFT, self::EMAIL_REPLACE);
			// mask part 2:
			$right = self::makeAsterix(strlen($parts[1])-self::EMAIL_SHOW_RIGHT, self::EMAIL_REPLACE) . substr($parts[1], strlen($parts[1])-self::EMAIL_SHOW_RIGHT);
			return $left."@".$right;
		} else {
			return "no valid email";
		}
	}

	private static function maskTel($tel) {
		$telMasked = substr($tel, 0,self::TEL_SHOW_LEFT);
		$telMasked .= self::makeAsterix(strlen($tel)-self::TEL_SHOW_LEFT-self::TEL_SHOW_RIGHT, self::TEL_REPLACE);
		$telMasked .= substr($tel, strlen($tel)-self::TEL_SHOW_RIGHT);
		return $telMasked;
	}

	private static function maskPwd($pwd) {
		return self::makeAsterix(strlen($pwd), self::PWD_REPLACE);
	}

	private static function maskAll($val) {
		return self::makeAsterix(strlen($val));
	}

	private static function makeAsterix($cnt, $what="*") {
		$x="";
		for ($i=0; $i < $cnt; $i++) { 
			$x.=$what;
		}
		return $x;
	}
}