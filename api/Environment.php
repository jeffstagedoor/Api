<?php
/**
*	Static Class Environment
*	
*	@author Jeff Frohner
*	@copyright Copyright (c) 2017
*	@license   private
*	@version   1.0
*
*/
namespace Jeff\Api;


/**
*	Static Class Environment
*	
*	@author Jeff Frohner
*	@copyright Copyright (c) 2017
*	@license   private
*	@version   1.0
*
*/
Class Environment
{
	public static $production = true;
	public static $development = false;
	public static $debug = false;
	public static $database = Array(
			"username" => "",
			"password" => "", 
			"host" => "",  
			"db" => ""
		);


		// default for noAuthRoutes: routes, that don't need to be authenticated
	public static $noAuthRoutes = Array(
		"login",
		"signup",
		"apiInfo",
		"getImage"
		);

	public static $urls;
	public static $dirs;
	public static $api;

	/**
	 * prevent constructor from beeing called to make this a static class
	 */
	public function __construct() {}

	/**
	 * initialize this class with default values
	 *
	 */
	public static function init(array $noAuthRoutes=null) {
		self::$urls = new \stdClass();
		self::$urls->baseUrl = "";
		self::$urls->appUrl = "";
		self::$urls->apiUrl = "api/";
		self::$urls->tasksUrl = "api/task/";
		self::$urls->allowOrigin = "";

		self::$dirs = new \stdClass();
		self::$dirs->appRoot = dirname(__FILE__).DIRECTORY_SEPARATOR.self::folderUp(4);
		self::$dirs->vendor = __DIR__.DIRECTORY_SEPARATOR.self::folderUp(3);
		self::$dirs->api = __DIR__.DIRECTORY_SEPARATOR;
		self::$dirs->models = self::$dirs->appRoot."models".DIRECTORY_SEPARATOR;
		self::$dirs->files = self::folderUp(2)."files".DIRECTORY_SEPARATOR;
		// $this->dirs->phpRoot = "..".DIRECTORY_SEPARATOR;

		self::$api = new \stdClass();
		self::$api->noAuth = false;
		if($noAuthRoutes) {
			self::$api->noAuthRoutes = array_merge(self::$noAuthRoutes, $noAuthRoutes);
		} 
	}


	/**
	 * adds an array of routes [string] to the local $noAuthRoutes.
	 * These routes don't need an authentication
	 * @param string[] $routes the array of routes as string eg. 'signup', 'task/acceptInvitation'
	 */
	public static function addNoAuthRoutes(array $routes) {
		self::$api->noAuthRoutes = array_merge(self::$noAuthRoutes, $routes);
	}

	/**
	 * adds one route [string] to the local $noAuthRoutes.
	 * These routes don't need an authentication
	 * @param string $route the routes eg. 'signup', 'task/acceptInvitation'
	 */
	public static function addNoAuthRoute(string $route) {
		self::$noAuthRoutes[] = $route;
	}

	/**
	 * simple helper, that generates '../' strings
	 * @param  int|integer $times how many folders we wanna go up
	 * @return string             f.e. '../../../' (if $times==3)
	 */
	public static function folderUp(int $times=1): string {
		$x="";
		for ($i=0; $i < $times; $i++) { 
			$x.="..".DIRECTORY_SEPARATOR;
		}
		return $x;
	}
}