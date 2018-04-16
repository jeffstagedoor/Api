<?php
/**
*	Static Class Environment
*	
*	@author Jeff Frohner
*	@copyright Copyright (c) 2017
*	@license   private
*	@version   1.0
*
* The consuming app __MUST__ include a file 'config.php' in it's route, that adapts these defaults.
* 
* The minimal implementation would be:
* 
* ```
* <?php
* use Jeff\Api\Environment;
* Environment::init();
* // no you can overwrite the settings (based on `$_SERVER['SERVER_NAME']` for example)
* Environment::$database = Array( 
*					"username" => "bar",
*					"password" => "foo", 
*					"host" => "localhost",  
*					"db" => "yourdatabase" 
*		);
* ```
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

	/**
	 * @var array Configuration for authorization
	 * 
	 * ```
	 * [
	 * 	"tokenType" => "1",
	 *	"authTokenExpiresIn" => "604800"
	 * ];
	 * ```
	 * 
	 */
	public static $authenticationConfig = [
		"tokenType" => "1",
		"authTokenExpiresIn" => "604800"
	];

	/** @var array default for noAuthRoutes: routes, that don't need to be authenticated 
	 *             simple array like `['login', 'signup', 'apiInfo', 'getImage']`
	 *             (these are the defaults)
	*/
	public static $noAuthRoutes = Array(
		"login",
		"signup",
		"apiInfo",
		"getImage"
		);

	public static $urls;
	public static $dirs;
	public static $api;
	public static $publicFolders = [];

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

		self::$dirs = new \stdClass();
		self::$dirs->appRoot = dirname(__FILE__).DIRECTORY_SEPARATOR.self::folderUp(4);
		self::$dirs->vendor = __DIR__.DIRECTORY_SEPARATOR.self::folderUp(3);
		self::$dirs->api = __DIR__.DIRECTORY_SEPARATOR;
		self::$dirs->models = self::$dirs->appRoot."models".DIRECTORY_SEPARATOR;
		self::$dirs->files = self::folderUp(2)."files".DIRECTORY_SEPARATOR;
		// $this->dirs->phpRoot = "..".DIRECTORY_SEPARATOR;

		self::$api = new \stdClass();
		self::$api->noAuth = false;
		self::$api->allowOrigin = "";
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

	public static function getConfig() {
		$config = new \stdClass();
		$config->urls = self::$urls;
		$config->dirs = self::$dirs;
		$config->api = self::$api;
		$config->production = self::$production;
		$config->development = self::$development;
		$config->debug = self::$debug;

		return $config;
	}
}