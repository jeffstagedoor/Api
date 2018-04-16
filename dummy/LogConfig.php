<?php
/**
* Customizing LOG Entries
*/
use Jeff\Api\Log\LogDefaultConfig;
use Jeff\Api\Log\LogDefaultFor;
use Jeff\Api\Log\LogDefaultMeta;


Class LogConfig extends LogDefaultConfig {

	protected static $path = "../LogApi";

	public static function values() {
		$values = new \stdClass();
		$values->posts = new \stdClass();
		$values->posts->for = new LogDefaultFor(
				NULL, \Constants::USER_ADMIN, 		//A
				NULL, Null,		// B
				NULL, NULL,		// C
				NULL, NULL 		// D
			);
		$values->posts->meta = new LogDefaultMeta(
				Array("posts", "id"),	// int
				null, // int
				null, // int
				Array("posts", "N_TITEL"), // string 80
				Array("posts", "N_TXT")	// string 255
			);

		return $values;
	}
}