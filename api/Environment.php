<?php
/**
*	Class Environment
*	
*	@author Jeff Frohner
*	@copyright Copyright (c) 2017
*	@license   private
*	@version   1.0
*
**/
namespace Jeff\Api;



Class Environment
{
	public $production = true;
	public $development = false;
	public $debug = false;
	public $database = Array(
			"username" => "",
			"password" => "", 
			"host" => "",  
			"db" => ""
		);
	// default for noAuthRoutes: routes, that don't need to be authenticated
	public $noAuthRoutes = Array(
		"login",
		"signup",
		"apiInfo",
		"getImage"
		);
	public $urls;
	public $dirs;
	public $api;

	function __construct(array $noAuthRoutes=null) {
		$this->urls = new \stdClass();
		$this->urls->baseUrl = "";
		$this->urls->appUrl = "";
		$this->urls->apiUrl = "api/";
		$this->urls->tasksUrl = "api/task/";
		$this->urls->allowOrigin = "";

		$this->dirs = new \stdClass();
		$this->dirs->appRoot = dirname(__FILE__).DIRECTORY_SEPARATOR.$this->folderUp(4);
		$this->dirs->vendor = __DIR__.DIRECTORY_SEPARATOR.$this->folderUp(3);
		$this->dirs->api = __DIR__.DIRECTORY_SEPARATOR;
		$this->dirs->models = $this->dirs->appRoot."models".DIRECTORY_SEPARATOR;
		$this->dirs->files = $this->folderUp(2)."files".DIRECTORY_SEPARATOR;
		// $this->dirs->phpRoot = "..".DIRECTORY_SEPARATOR;

		$this->api = new \stdClass();
		$this->api->noAuth = false;
		if($noAuthRoutes) {
			$this->api->noAuthRoutes = array_merge($this->noAuthRoutes, $noAuthRoutes);
		} 
		#var_dump($this->api->noAuthRoutes);

	}

	public function addNoAuthRoute(string $route) {
		$this->noAuthRoutes[] = $route;
	}

	public function folderUp(int $times=1): string {
		$x="";
		for ($i=0; $i < $times; $i++) { 
			$x.="..".DIRECTORY_SEPARATOR;
		}
		return $x;
	}
}