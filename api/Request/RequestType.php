<?php
/**
 * abstract class to define Request Types
 * 
 */
namespace Jeff\Api\Request;

abstract class RequestType {
	Const NORMAL = 1;
	Const REFERENCE = 2;
	Const COALESCE = 3;
	Const QUERY = 4;
	Const SPECIAL = 5;
	Const INFO = 6;
}