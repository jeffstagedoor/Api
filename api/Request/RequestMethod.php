<?php
/**
 * abstract class to define Request Methods (=Http-Methods)
 * 
 * Specifications:
 * https://tools.ietf.org/html/rfc7231#section-4
 * PATCH update:
 * https://tools.ietf.org/html/rfc5789#section-2
 */
abstract class RequestMethod extends SplEnum {
	Const GET = 1;
	Const HEAD = 2;
	Const POST = 3;
	Const PUT = 4;
	Const DELETE = 5;
    Const CONNECT = 6;
    Const OPTIONS = 7;
    Const TRACE = 8;
    Const PATCH = 9;

    
    public function findMethod() {
        switch($_SERVER['REQUEST_METHOD']) {
            case 'GET':
                return RequestMethod::GET;
            case 'HEAD':
                return RequestMethod::HEAD;
            case 'POST':
                return RequestMethod::POST;
            case 'PUT':
                return RequestMethod::PUT;
            case 'DELETE':
                return RequestMethod::DELETE;
            case 'CONNECT':
                return RequestMethod::CONNECT;
            case 'OPTIONS':
                return RequestMethod::OPTIONS;
            case 'TRACE':
                return RequestMethod::TRACE;
            case 'PATCH':
                return RequestMethod::PATCH;
        }
    }
}