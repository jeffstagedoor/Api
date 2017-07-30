<?php
/********
* Customizing LOG Entries
*/

namespace Jeff\Api;
require_once('Constants.php');




global $logConfig;
$logConfig = new \stdClass();


$logConfig->logPath = __DIR__.DIRECTORY_SEPARATOR."apiLog".DIRECTORY_SEPARATOR;
$logConfig->dbTable = "log";

// POSTS
$logConfig->posts = new \stdClass();
$logConfig->posts->for = new \Jeff\Api\LogDefaultFor(
			NULL, \Jeff\Constants::USER_ADMIN, 		//A
			NULL, NULL,	// B
			NULL, NULL,		// C
			NULL, NULL 		// D
		);
$logConfig->posts->meta = new \Jeff\Api\LogDefaultMeta(
			Array("posts", "id"),	// int
			null, // int
			null, // int
			Array("posts", "N_TITEL"), // string 80
			Array("posts", "N_TXT")	// string 255
		);

// REFERENZEN