<?php
/*
/	FileUpload Configuration File
/
*/

// this is the config-json for what to save in DB and where:
$jsonConfigItemTypes = '{
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
$configItemTypes = json_decode($jsonConfigItemTypes, true);