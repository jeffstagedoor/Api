<?php

namespace Jeff\Api;


Class Log {
	public $db = NULL;
	private $logConfig;

	public function __construct($db) {
		$this->db = $db;

		require_once("../config.php");
		global $ENV, $logConfig;
		if (!file_exists(__DIR__.DIRECTORY_SEPARATOR.$ENV->dirs->appRoot."LogConfig.php")) {
			echo "no LogConfig File found in '{$ENV->dirs->appRoot}LogConfig.php'. Should be placed in php-Root folder.";
		} else {
			require_once(__DIR__.DIRECTORY_SEPARATOR.$ENV->dirs->appRoot."LogConfig.php");
			$this->logConfig = $logConfig;
		}
	}

	/** Basic Write to Log - Method, will be overridden by special logs
	*
	*
	*/
	public function write($user, $type, $itemName, $data) {
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
		// echo "<h3>FOR</h3>\n\n";
		// var_dump($for);
		// echo "\n<h3>META</h3>\n\n";
		// var_dump($meta);

		$data = new \stdClass();
		$data->user = $user;
		$data->type = $type;
		$data->item = $itemName;


		$dbData = array_merge((Array) $data, (Array) $for, (Array) $meta);
		// echo "\n<h3>dbData</h3>\n\n";
		// var_dump($dbData);
		
		$id = $this->db->insert("log", $dbData);
		// echo "SQL: ".$this->db->getLastQuery()."\n";
		// echo "Error: ".$this->db->getLastError()."\n";
		// echo "\nid:".$id."\n";
		return $id;		
		// return false;
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


	/**
	*
	*/
	public function getUserAgent() {
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
	public function getGeoInfo($ip) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "http://ipinfo.io/".$ip."/json");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$json = curl_exec($ch);
		curl_close($ch);
		$obj = json_decode($json);
		if(is_object($obj)) return $obj;
		else return false;
	}

	public function addGeoInfoToData($ip, $data) {
		$geoInfo = $this->getGeoInfo($ip);
		if($geoInfo) {
			if(isset($geoInfo->loc)) {
				$longlat = explode(",",$geoInfo->loc);
				$data['long'] = $longlat[0];
				$data['lat'] = $longlat[1];
			}
			$data['geoCity'] = isset($geoInfo->city) ? $geoInfo->city : "";
			$data['geoRegion'] =  isset($geoInfo->region) ? $geoInfo->region : "";
			$data['geoCountry'] =  isset($geoInfo->country) ? $geoInfo->country : "";
			$data['geoOrg'] =  isset($geoInfo->org) ? $geoInfo->org : "";
			$data['geoPostal'] =  isset($geoInfo->postal) ? $geoInfo->postal : "";
		}
		return $data;
	}
}






Class LoginLog extends Log{
	private $user = NULL;
	private $loginattempt = false;
	private $success = false;

	public function writeLoginLog($user, $loginattempt, $success) {
		$this->user = $user;
		$this->loginattempt = $loginattempt;
		$this->success = $success;
		$dbData = $this->collectData();
		$id = $this->db->insert("loglogin", $dbData);
		return $id;
	}


	private function collectData() {
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

		// add geo-information (will fail gracefully and return same data as passed if we are on localhost or have no ip)
		$data = $this->addGeoInfoToData($ip, $data);
		return $data;
	}
}


// These are the default classes that shall be used by LogConfig.php
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