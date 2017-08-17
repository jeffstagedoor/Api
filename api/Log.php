<?php
/**
*	Class Log
*	
*	@author Jeff Frohner
*	@copyright Copyright (c) 2015
*	@license   private
*	@version   1.0.0
*
**/

namespace Jeff\Api;


Class Log {
	protected $db = NULL;
	protected $logConfig;
	protected $readyToWrite = false;
	protected $ENV = NULL;
	protected $errorHandler = NULL;
	public $modelName = "Log";

	protected $dbTable = "log";
	public $dbDefinition = Array(
			array ('id', 'int', '11', false, NULL, 'auto_increment'),
			array ('A', 'int', '11', true, NULL),
			array ('ARights', 'int', '11', true, NULL),
			array ('B', 'int', '11', true, NULL),
			array ('BRights', 'int', '11', true, NULL),
			array ('C', 'int', '11', true, NULL),
			array ('CRights', 'int', '11', true, NULL),
			array ('D', 'int', '11', true, NULL),
			array ('DRights', 'int', '11', true, NULL),

			array ('type', 'varchar', '50', false),
			array ('item', 'varchar', '50', true, NULL),

			array ('meta1', 'int', '11', true, NULL),
			array ('meta2', 'int', '11', true, NULL),
			array ('meta3', 'int', '11', true, NULL),
			array ('meta4', 'varchar', '80', true, NULL),
			array ('meta5', 'varchar', '255', true, NULL),

			array ('logDate', 'timestamp', null, false, 'CURRENT_TIMESTAMP'),
			array ('user', 'int', '11', true, NULL),

		);
	public $dbPrimaryKey = 'id';

	public function __construct($db, $ENV, $errorHandler) {
		$this->db = $db;
		$this->ENV = $ENV;
		$this->errorHandler=$errorHandler;
	
		if(!$this->errorHandler) { $this->errorHandler = new ErrorHandler(); }
		if (!file_exists($this->ENV->dirs->appRoot."LogConfig.php")) {
			$this->errorHandler->add(new Error(ErrorHandler::LOG_NO_CONFIG));
			$this->errorHandler->sendErrors();
			$readyToWrite = false;
		} else {
			include_once($this->ENV->dirs->appRoot."LogConfig.php");
			$this->logConfig = \LogConfig::values();
			$readyToWrite = true;
		}

		// check if we have a database ready:
		try {
			$this->db->connect();
		} catch(\Exception $e) {
			$this->db = NULL;
			$this->readyToWrite = false;
			$this->errorHandler->add(Array("DB Error", "Could not connect to database", 500, true, ErrorHandler::CRITICAL_ALL, $e));
			$this->errorHandler->sendErrors();
			exit;
		}


		$logTable = $this->db->rawQuery("SHOW tables like '".\LogConfig::DB_TABLE."'");
		// echo "SHOW tables like '".\Jeff\LogConfig::DB_TABLE."'";
		// echo "logTable:";
		// var_dump($logTable);
		if(count($logTable)>0) { 
			$this->readyToWrite=true;
		} else {
			$this->readyToWrite = false;
			$this->errorHandler->add(new Error(ErrorHandler::LOG_NO_TABLE));
			$this->errorHandler->sendErrors();
		}
	}

	/** Basic API Write to DBLog - Method, will be overridden by special logs
	*
	*
	*/
	public function write($user, $type, $itemName, $data) {
		if(!$this->readyToWrite) { 
			return null;
		}
		// first Prepare from custom LogConfig
		// so let's see if we have a configuration for the given item:
		if (isset( $this->logConfig->{$itemName} )) {
			$for = $this->extractDataFromConfig($this->logConfig->{$itemName}->for, $data);
			$meta = $this->extractDataFromConfig($this->logConfig->{$itemName}->meta, $data);
		} else {
			// fallback to default
			$logForConfig = new LogDefaultFor(NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL);
			$logMetaConfig = new LogDefaultMeta(Array($itemName,"id"),NULL,NULL,NULL,NULL);
			$for = $this->extractDataFromConfig($logForConfig, $data);
			$meta = $this->extractDataFromConfig($logMetaConfig, $data);
		}

		$data = new \stdClass();
		$data->user = $user;
		$data->type = $type;
		$data->item = $itemName;


		$dbData = array_merge((Array) $data, (Array) $for, (Array) $meta);
		// var_dump($this->logConfig);
		// var_dump($dbData);
		// var_dump($this->db)
		$id = $this->db->insert($this->dbTable, $dbData);
		return $id;		
	}


	private function extractDataFromConfig($logConfig, $data) {
		foreach ($logConfig as $key => $value) {
			if(is_array($value)) {
				// extract specified values from $data
				if(isset($data->{$value[0]}[$value[1]])) {
					$v = $data->{$value[0]}[$value[1]];
				} else {
					$v = null;
				}
				$logConfig->{$key} = $v;
			}
		}
		return $logConfig;		
	}


	protected function collectData() {
		// get some infos bout browser, os, ....
		$ua = $this->getUserAgent();
		// get and check ip-adress
		$ip  = $_SERVER['REMOTE_ADDR'];
		$ip4 = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $ip : "";
		$ip6 = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? $ip : "";

		$data = Array(
			'user' => $this->user,
			'loginattempt' => $this->loginattempt,
			'success' => $this->success,
			'timestamp' => $this->db->now(),
			'referer' => '',
			'userAgent' => $ua['userAgent'],
			'userAgentOs' => $ua['platform'],
			'userAgentBrowser' => $ua['browser'] ." ". $ua['version'],
			'ip4' => $ip4,
			'ip6' => $ip6
		);

		$geoInfo = $this->getGeoInfoArray();
		$data = array_merge($data, $geoInfo);
		return $data;
	}


	/**
	*
	*/
	public static function getUserAgent() {
		$u_agent = $_SERVER['HTTP_USER_AGENT']; 
		$bname = 'Unknown';
		$platform = 'Unknown';
		$version= "";

		//First get the platform?
		if (preg_match('/linux/i', $u_agent)) {
			$platform = 'linux';
		}
		elseif (preg_match('/macintosh|mac os x/i', $u_agent)) {
			$platform = 'mac';
		}
		elseif (preg_match('/windows|win32/i', $u_agent)) {
			$platform = 'Windows';
			if (preg_match('/NT 6.2/i', $u_agent)) { $platform .= ' 8'; }
			elseif (preg_match('/NT 10.0/i', $u_agent)) { $platform .= ' 10'; }
			elseif (preg_match('/NT 6.3/i', $u_agent)) { $platform .= ' 8.1'; }
			elseif (preg_match('/NT 6.1/i', $u_agent)) { $platform .= ' 7'; }
			elseif (preg_match('/NT 6.0/i', $u_agent)) { $platform .= ' Vista'; }
			elseif (preg_match('/NT 5.1/i', $u_agent)) { $platform .= ' XP'; }
			elseif (preg_match('/NT 5.0/i', $u_agent)) { $platform .= ' 2000'; }
			#if (preg_match('/WOW64/i', $u_agent) || preg_match('/x64/i', $u_agent)) { $platform .= ' (x64)'; }
		}
		
		// Next get the name of the useragent yes seperately and for good reason
		if(preg_match('/MSIE/i',$u_agent) && !preg_match('/Opera/i',$u_agent)) 
		{ 
			$bname = 'Internet Explorer'; 
			$ub = "MSIE"; 
		} 
		elseif(preg_match('/Firefox/i',$u_agent)) 
		{ 
			$bname = 'Mozilla Firefox'; 
			$ub = "Firefox"; 
		} 
		elseif(preg_match('/Chrome/i',$u_agent)) 
		{ 
			$bname = 'Google Chrome'; 
			$ub = "Chrome"; 
		} 
		elseif(preg_match('/Safari/i',$u_agent)) 
		{ 
			$bname = 'Apple Safari'; 
			$ub = "Safari"; 
		} 
		elseif(preg_match('/Opera/i',$u_agent)) 
		{ 
			$bname = 'Opera'; 
			$ub = "Opera"; 
		} 
		elseif(preg_match('/Netscape/i',$u_agent)) 
		{ 
			$bname = 'Netscape'; 
			$ub = "Netscape"; 
		} 
		
		// finally get the correct version number
		$known = array('Version', $ub, 'other');
		$pattern = '#(?<browser>' . join('|', $known) .
		')[/ ]+(?<version>[0-9.|a-zA-Z.]*)#';
		if (!preg_match_all($pattern, $u_agent, $matches)) {
			// we have no matching number just continue
		}
		
		// see how many we have
		$i = count($matches['browser']);
		if ($i != 1) {
			//we will have two since we are not using 'other' argument yet
			//see if version is before or after the name
			if (strripos($u_agent,"Version") < strripos($u_agent,$ub)){
				$version= $matches['version'][0];
			}
			else {
				$version= $matches['version'][1];
			}
		}
		else {
			$version= $matches['version'][0];
		}
		
		// check if we have a number
		if ($version==null || $version=="") {$version="?";}
		
		return array(
			'userAgent' => $u_agent,
			'browser'   => $bname,
			'version'   => $version,
			'platform'  => $platform,
			'pattern'    => $pattern
		);

	}
	// End getUserAgent


	/**
	*	getGeoInfo()
	*	@param $ip
	*	@return object of infos
	*/
	public static function getGeoInfo() {
		$ip  = $_SERVER['REMOTE_ADDR'];
		// $ip = "213.168.109.88";
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "http://ipinfo.io/".$ip."/json");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$json = curl_exec($ch);

		curl_close($ch);
		$obj = json_decode($json);
		if(is_object($obj)) return $obj;
		else return false;
	}

	public static function getGeoInfoArray() {
		$geoInfo = self::getGeoInfo();
		$g = Array();
		if($geoInfo) {
			if(isset($geoInfo->loc)) {
				$longlat = explode(",",$geoInfo->loc);
				$g['long'] = $longlat[0];
				$g['lat'] = $longlat[1];
			}
			$g['geoCity'] = isset($geoInfo->city) ? $geoInfo->city : "";
			$g['geoRegion'] =  isset($geoInfo->region) ? $geoInfo->region : "";
			$g['geoCountry'] =  isset($geoInfo->country) ? $geoInfo->country : "";
			$g['geoOrg'] =  isset($geoInfo->org) ? $geoInfo->org : "";
			$g['geoPostal'] =  isset($geoInfo->postal) ? $geoInfo->postal : "";
		}
		return $g;
	}

	public function getDbTable() {
		return $this->dbTable;
	}
}


Class LogLogin extends Log {
	public $modelName = "LogLogin";

	// private $dbTable = \Jeff\LogConfig::DB_TABLE_LOGIN;
	protected $dbTable = "loglogin";
	public $dbDefinition = Array(
			array ('id', 'int', '11', false, false, 'auto_increment'),
			array ('user', 'int', '11', false),
			array ('loginattempt', 'tinyint', '1', false),
			array ('success', 'tinyint', '1', false),
			array ('timestamp', 'timestamp', null, false, 'CURRENT_TIMESTAMP', 'ON UPDATE CURRENT_TIMESTAMP'),
			
			array ('referer', 'varchar', '150', false),
			array ('userAgent', 'varchar', '150', false),
			array ('userAgentOs', 'varchar', '30', false),
			array ('userAgentBrowser', 'varchar', '50', false),
			array ('ip4', 'varchar', '15', false),
			array ('ip6', 'varchar', '39', false),

			array ('long', 'int', '11', false),
			array ('lat', 'int', '11', false),
			
			array ('geoCity', 'varchar', '50', false),
			array ('geoRegion', 'varchar', '50', false),
			array ('geoCountry', 'varchar', '10', false),
			array ('geoOrg', 'varchar', '50', false),
			array ('geoPostal', 'varchar', '15', false),
		);
	public $dbPrimaryKey = 'id';

	public function writeLoginLog($user, $loginattempt, $success) {
		$this->user = $user;
		$this->loginattempt = $loginattempt;
		$this->success = $success;
		$dbData = $this->collectData();
		// echo "SHOW FULL TABLES LIKE '".\Jeff\LogConfig::DB_TABLE_LOGIN."'<br>\n";
		$result = $this->db->rawQuery("SHOW FULL TABLES LIKE '".\LogConfig::DB_TABLE_LOGIN."'");
		// var_dump($result);
		if(count($result)>0) {
			// echo "count > 0 : ".count($result)."<br>\n";
			$id = $this->db->insert(\LogConfig::DB_TABLE_LOGIN, $dbData);
			return $id;
		} else {
			// echo "count NOT > 0 : ".count($result)."<br>\n";
			$this->errorHandler->add(new Error(ErrorHandler::LOG_NO_TABLE_LOGIN));
			$this->errorHandler->sendErrors();
			// exit;
		}
	}
}



// These are the default classes that shall be used by LogConfig.php
Class LogDefaultConfig {
	const PATH = 'apiLog';
	const DB_TABLE = "log";
	const DB_TABLE_LOGIN = "loglogin";

	public static function values() {
		$values = new \stdClass();
		return $values;
	}

	public static function getPath() {
		return dirname(__FILE__).DIRECTORY_SEPARATOR.self::PATH.DIRECTORY_SEPARATOR;;
	}

	public static function getDbTable() {
		return self::DB_TABLE;
	}
}
Class LogDefaultFor {
	public $A;
	public $ARights;
	public $B;
	public $BRights;
	public $C;
	public $CRights;
	public $D;
	public $DRights;

	function __construct($A, $ARights, $B, $BRights, $C, $CRights, $D, $DRights) {
		$this->A 		= $A;
		$this->ARights 	= $ARights;
		$this->B 		= $B;
		$this->BRights 	= $BRights;
		$this->C 		= $C;
		$this->CRights 	= $CRights;
		$this->D 		= $D;
		$this->DRights 	= $DRights;
	}
}

Class LogDefaultMeta {
	public $Meta1;
	public $Meta2;
	public $Meta3;
	public $Meta4;
	public $Meta5;

	function __construct($A=null, $B=null, $C=null, $D=null, $E=null) {
		$this->Meta1 = $A;
		$this->Meta2 = $B;
		$this->Meta3 = $C;
		$this->Meta4 = $D;
		$this->Meta5 = $E;
	}
}