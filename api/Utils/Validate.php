<?php

namespace Jeff\Api\Utils;



// TO DO A LOT !!!!!

// BUT: DO I really need that??

Class Validate {

	const VAL_TYPE_EMAIL = 'email';
	const VAL_TYPE_TEL = 'tel';
	const VAL_TYPE_LENGTH_MIN = 'lenmin';
	const VAL_TYPE_LENGTH_MAX = 'lenmax';

	public static function email($email) {
		return preg_match("/^[_a-zA-Z0-9-]+(.[_a-zA-Z0-9-]+)*@[a-zA-Z0-9-.]+.([a-zA-Z]{2,4})$/", $email); 
	}

	public static function lengthMin($string, $min) {
		return (strlen($string)>$min);
	}

	public static function validate($value, $valtype, $arg) {
		switch ($valtype) {
			case self::VAL_TYPE_EMAIL: 
				return false;
				break;
			case self::VAL_TYPE_TEL: 
				return false;
				break;
			case self::VAL_TYPE_LENGTH_MAX: 
				return false;
				break;
			case self::VAL_TYPE_LENGTH_MIN: 
				return false;
				break;
		}
	}
}