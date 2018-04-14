<?php
/**
 * abstract class to define Request Types
 * 
 */
abstract class RequestType extends SplEnum {
	Const NORMAL = 1;
	Const REFERENCE = 2;
	Const COALESCE = 3;
	Const QUERY = 4;
	Const SPECIAL = 5;
	Const INFO = 6;
}