<?php
/**
*	@description Configuration file
*	@author Jeff Frohner
*	@version 1.0.0
*/


$ENV = new \stdClass();

// defaults (=production)
$ENV->production = true;
$ENV->development = false;
$ENV->debug = false;

$ENV->database = Array( 
			"username" => "",
			"password" => "", 
			"host" => "",  
			"db" => "" 
);

$ENV->urls = new \stdClass();
$ENV->urls->baseUrl = "http://www.example.de";
$ENV->urls->appUrl = "";
$ENV->urls->apiUrl = "api/";
$ENV->urls->tasksUrl = "api/tasks/";
$ENV->urls->allowOrigin = "";

$ENV->dirs = new \stdClass();
$ENV->dirs->appRoot = folderUp(1)."dummy".DIRECTORY_SEPARATOR;
$ENV->dirs->vendor = folderUp(1)."vendor".DIRECTORY_SEPARATOR;
$ENV->dirs->models = $ENV->dirs->appRoot."models".DIRECTORY_SEPARATOR;
$ENV->dirs->files = folderUp(2)."files".DIRECTORY_SEPARATOR;

$ENV->Api = new \stdClass();
$ENV->Api->noAuthRoutes = Array(
	"login",
	"signup",
	"apiinfo",
	"getimage",
	"tasks/user2artistconfirmation"
	);


switch ($_SERVER['SERVER_NAME']) {
	case 'dummy':
	case 'localhost':
	case '127.0.0.1':
		$ENV->production = false;
		$ENV->development = true;
		$ENV->debug = false;
		$ENV->Api->noAuth = true;

		$ENV->database = Array( 
					"username" => "root",
					"password" => "", 
					"host" => "localhost",  
					"db" => "apidummy" 
		);

		$ENV->urls->baseUrl = "http://127.0.0.1/jeffstagedoor/php/Api/dummy/";
		$ENV->urls->appUrl = "dist/";
		$ENV->urls->allowOrigin = "http://localhost:4200";  // where Api-calls may be from exept same host

		// $ENV->urls->apiUrl = "api/"; // is default
		// $ENV->urls->tasksUrl = "api/tasks/"; // is default
		// $ENV->dirs->files = folderUp(2)."files".DIRECTORY_SEPARATOR; // is default

		break;
	case 'www.example2.com':
		$ENV->production = false;
		$ENV->development = false;
		$ENV->debug = true;

		$ENV->database = Array( 
					"username" => "",
					"password" => "", 
					"host" => "localhost",  
					"db" => "dummy" 
		);

		$ENV->urls->baseUrl = "http://www.example2.com";
		$ENV->urls->appUrl = "";
		$ENV->urls->apiUrl = "api/";
		$ENV->urls->tasksUrl = "api/tasks/";

		$ENV->dirs->files = folderUp(3)."files".DIRECTORY_SEPARATOR;

		break;

}


function folderUp($times=1) {
	$x="";
	for ($i=0; $i < $times; $i++) { 
		$x.="..".DIRECTORY_SEPARATOR;
	}
	return $x;
}
