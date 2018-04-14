<?php
/**
* Customizing FILEUPLOADS
*
*
*/

use Jeff\Api\FileDefaultConfig;

Class FileConfig extends FileDefaultConfig {
	public static $itemTypes = '{
	"tmp" : {
		"reference": null,
		"dbtable": null,
		"dbid": null,
		"labelfield": null
	},
	"user" : { 
		"reference": "users", 
		"dbtable": "users",
		"dbid": "id",
		"labelfield": "fullName"
	},
	"artist" : { 
		"reference": "artists", 
		"dbtable": "artists",
		"dbid": "id",
		"labelfield": "fullName"
	},
	"production" : { 
		"reference": "productions", 
		"dbtable": "productions",
		"dbid": "id",
		"labelfield": "label"
	}

}';
}