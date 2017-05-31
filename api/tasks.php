<?php
/**
*	File tasks
*	
*	@author Jeff Frohner
*	@copyright Copyright (c) 2015
*	@license   private
*	@version   0.8
*
**/



namespace Jeff\Api;

function task($type, $data) {
	global $Account, $db, $ENV;

	// this script can/should only be called from api.php
	// if no authorized user is available, exit script.

	if(!$Account->isAuthenticated) {
		echo "FATAL ERROR: NOT AUTHENTICATED";
		exit;
	}
	// depending on what kind of special task is demanded, do what we need to do...
	// which is defined in this File:
	if (!file_exists($modelFile)) {
			echo "API Error: No customTasks file implemented";
	} else {
			include($ENV->dirs->phpRoot."customTasks.php");
	}
	
}

function gotoErrorPage($error, $type='') {
	global $ENV;
	$err = json_decode($error);
	#echo "location: ".$ENV->urls->baseUrl.$ENV->urls->appUrl.'publicLinks/error?type='.$type.'&msg='.urlencode($err->errors[0]->msg);
	header("location: ".$ENV->urls->baseUrl.$ENV->urls->appUrl.'publicLinks/error?type='.$type.'&msg='.urlencode($err->errors[0]->msg));
}


function GetRandomString($length) {
	$template = "1234567890abcdefghijklmnopqrstuvwxyz";
	settype($length, "integer");
	settype($rndstring, "string");
	settype($a, "integer");
	settype($b, "integer");
	for ($a = 0; $a <= $length; $a++) {
		   $b = rand(0, strlen($template) - 1);
		   $rndstring .= $template[$b];
	}
	return $rndstring;
}