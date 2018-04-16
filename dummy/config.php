<?php
/**
* Configuration file
*
* This File describes the basic api configuration such as database credentials, folder structure,
* debug switches, urls, ..
* 
* This is obligatory.
* 
* @author Jeff Frohner
* @version 2.0.0
*/

use Jeff\Api\Environment;

Environment::init();
// add routes that don't need authentication
Environment::addNoAuthRoutes([
					"task/acceptInvitation",
					"task/account2workgroupInvitationGetData",
					"task/account2workgroupInvitationAcception"
					]);


switch ($_SERVER['SERVER_NAME']) {
	case 'dummy':
	case 'localhost':
	case '127.0.0.1':
		Environment::$production = false;
		Environment::$development = true;
		Environment::$debug = false;
		Environment::$api->noAuth = true;
		Environment::$api->allowOrigin = "http://localhost:4200";  // where Api-calls may be from exept same host

		Environment::$database = Array( 
					"username" => "root",
					"password" => "", 
					"host" => "localhost",  
					"db" => "apidummy" 
		);

		Environment::$urls->baseUrl = "http://127.0.0.1/jeffstagedoor/php/Api/dummy/";
		Environment::$urls->appUrl = "dist/";
		Environment::$dirs->vendor = Environment::folderUp(2)."vendor/";
		Environment::$dirs->appRoot = __DIR__.DIRECTORY_SEPARATOR;
		Environment::$dirs->api = __DIR__.Environment::folderUp(2)."api".DIRECTORY_SEPARATOR;
		Environment::$dirs->models = Environment::$dirs->appRoot."models".DIRECTORY_SEPARATOR;
		Environment::$dirs->files = Environment::folderUp(2)."files".DIRECTORY_SEPARATOR;


		// $ENV->urls->apiUrl = "api/"; // is default
		// $ENV->urls->tasksUrl = "api/tasks/"; // is default
		// $ENV->dirs->files = folderUp(2)."files".DIRECTORY_SEPARATOR; // is default

		break;
	case 'www.example2.com':
		Environment::$production = false;
		Environment::$development = false;
		Environment::$debug = true;

		Environment::$database = Array( 
					"username" => "",
					"password" => "", 
					"host" => "localhost",  
					"db" => "dummy" 
		);

		Environment::$urls->baseUrl = "http://www.example2.com";
		Environment::$urls->appUrl = "";
		Environment::$urls->apiUrl = "api/";
		Environment::$urls->tasksUrl = "api/tasks/";

		Environment::$dirs->files = folderUp(3)."files".DIRECTORY_SEPARATOR;

		break;

}