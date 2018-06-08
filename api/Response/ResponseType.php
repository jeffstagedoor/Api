<?php
/**
 * abstract class to define Response Types
 * 
 */
abstract class ResponseType extends SplEnum {
	Const EMPTY = 0;
	Const SUCCESS = 1;
	Const ERROR = 2;
}