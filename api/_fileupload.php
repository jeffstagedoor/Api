<?php
#########################
#
# fileupload.php
#
# FILEUPLOAD Private Interface
#
# copy Jeff Frohner 2015
#
#########################
namespace Jeff\Api;


// echo 'post_max_size = ' . ini_get('post_max_size') . "\n";
// echo 'memory_limit = ' . ini_get('memory_limit') . "\n";
// echo 'max_execution_time = ' . ini_get('max_execution_time') . "\n";
// echo 'max_input_time = ' . ini_get('max_input_time') . "\n";
// echo 'upload_max_filesize = ' . ini_get('upload_max_filesize') . "\n";

require_once('../config.php');

// These Parameters can be overridden in php_root/FileConfig.php
$maxNrOfFiles = 1; 				// for now I go with only 1 file
$baseFileParamName = 'file'; 	// the param-name of the file as it comes from html-form, as fetched in JeffFileUpload.js->startUpload();

$fileNameAddonId = true;
$fileNameAddonType = true;
$fileNameAddonLabel = true;
$includeItemTypeToFilePath = true;
$includeTypeToFilePath = true;

$fulProperties = new \stdClass();
// these are the defaults (in FileManager), that can be overridden that way:
// $fulProperties->allowedExtensions = Array(
// 		'jpg', 'jpeg', 'png',
// 		'mp3', 'aac', 'mp4', 'mpeg',
// 		'pdf', 'txt', 'doc', 'docx', 'xls', 'xlsx',
// 		'sib'
// );
// $fulProperties->allowedMimeTypes = Array(
// 	'text/plain',
//	'image/jpeg',
// 	'application/octet-stream', // f.e. xls
// 	'application/pdf', // f.e. xls
// );
// $maxMegaByte = 5;
// $fulProperties->maxFileSize = $maxMegaByte*1024*1024; // in Byte
$fulProperties->targetFolder = $ENV->dirs->files;

// ItemTypes
$configItemTypes = array();
// $jsonConfigItemTypes = '{
// 	"tmp" : {
// 		"reference": null,
// 		"dbTable": null,
// 		"dbId": null,
// 		"labelField": null
// 	},
// 	"user" : { 
// 		"reference": "users", 
// 		"dbTable": "users",
// 		"dbId": "id",
// 		"labelField": "fullName"
// 	}
// }';
// $configItemTypes = json_decode($jsonConfigItemTypes, true);

include_once("../../../../FileConfig.php");
// END OF DEFAULTS AND CONFIGURATION




if(isset($_FILES[$baseFileParamName])) {
	$myFile = $_FILES[$baseFileParamName];
} else {
	$myFile = null;
}

// getting possible params from post
$type = isset($_POST['type']) ? $_POST['type'] : null;
$itemType = isset($_POST['itemType']) ? $_POST['itemType'] : null;
$refId = isset($_POST['refId']) ? $_POST['refId'] : null;
$setas = (isset($_POST['setas']) && $_POST['setas']!='undefined') ? $_POST['setas'] : null;
// getting crop-values:
$crop = new \stdClass();
$crop->doCrop = (isset($_POST['crop']) && $_POST['crop']==='true') ? true : false;
$crop->x = (isset($_POST['cropX'])) ? $_POST['cropX'] : null;
$crop->y = (isset($_POST['cropY'])) ? $_POST['cropY'] : null;
$crop->width = (isset($_POST['cropWidth'])) ? $_POST['cropWidth'] : null;
$crop->height = (isset($_POST['cropHeight'])) ? $_POST['cropHeight'] : null;
// not used til now:
// $crop->rotate = (isset($_POST['cropRotate'])) ? $_POST['cropRotate'] : null;
// $crop->scaleX = (isset($_POST['cropScaleX'])) ? $_POST['cropScaleX'] : null;
// $crop->scaleY = (isset($_POST['cropY'])) ? $_POST['cropScaleY'] : null;

$maxWidth = (isset($_POST['maxWidth']) && $_POST['maxWidth']!='undefined') ? $_POST['maxWidth'] : null;
$maxHeight = (isset($_POST['maxHeight']) && $_POST['maxHeight']!='undefined') ? $_POST['maxHeight'] : null;

// set properties
$fsaveProperties = new \stdClass();
$fsaveProperties->type=$type;
$fsaveProperties->itemType=$itemType;
$fsaveProperties->setas = $setas;
$fsaveProperties->refId = $refId;



require_once('FileManager.php');
$fulManager = new FileManager($myFile, $fulProperties);

if(!$fulManager->isFileOk() ) {
// File is not Ok -> respond with errors as json
	echo '{"errors": ' . json_encode($fulManager->errors) . '}';
	exit;
}



if($type==='tmp') {
	// in that case we only need to save that file/image and return it's path so that it can be displayed to be processed further
	$fsaveProperties->addonPath = "tmp";
	$tmpFile = $fulManager->saveFile($myFile, $fsaveProperties);
	$tmpFile->path = 'tmp';
	$tmpFile->type = 'tmp';
	echo json_encode($tmpFile);
	exit;
}



if($itemType) {
	$fsaveProperties->addonType = ($fileNameAddonType) ? $itemType : null;
	$reference = isset($configItemTypes[$itemType]['reference']) ? $configItemTypes[$itemType]['reference'] : '';
	$fsaveProperties->addonPath = ($includeItemTypeToFilePath) ? $itemType : null;
	$fsaveProperties->addonPath = ($includeTypeToFilePath) ? $fsaveProperties->addonPath.DIRECTORY_SEPARATOR.$type : null;
} 

if($refId) {
	$fsaveProperties->refId = $refId;

	if($fileNameAddonId) $fsaveProperties->addonId = $refId;

	if($fileNameAddonLabel && isset($t) && isset($configItemTypes[$t]['labelfield'])) {
		// trying to get the specified label (in configItemTypes) from DB
		$configs = $configItemTypes[$t];
		$db->where(  $configs['dbid'] ,  intval($refId) );
		$result = $db->get( $configs['dbtable']);
		if($result) {
			$fsaveProperties->addonLabel = $result[0][$configs['labelfield']];
		}
	}
}

// SAVING AND MOVING THE FILE
$newFile = $fulManager->saveFile($myFile, $fsaveProperties);
if(!$newFile) {
	echo "{'errors': [ {'msg': 'file could not be moved to target folder'}]\n";
	exit;
}
#var_dump($newFile);

// Now CROP an IMAGE if this was set
if($crop->doCrop && $fulManager->isImage()) {
	require_once($ENV->dirs->phpRoot.$ENV->dirs->classes.'Image.php');
	$image = new Image(realpath($newFile->path).DIRECTORY_SEPARATOR.$newFile->name);
	$image->crop($crop->x, $crop->y, $crop->width, $crop->height);
	$image->save(realpath($newFile->path), $newFile->name, $image->getType());
	$newFile->imageSize = $image->getSize();
	$newFile->size = filesize(realpath($newFile->path).DIRECTORY_SEPARATOR.$newFile->name);
}

// if a maxWidth or maxHeight was passed resize the image
if( ($maxWidth || $maxHeight) && $fulManager->isImage()) {
	require_once($ENV->dirs->phpRoot.$ENV->dirs->classes.'Image.php');
	$image = new Image(realpath($newFile->path).DIRECTORY_SEPARATOR.$newFile->name);
	$image->resizeMax($maxWidth, $maxHeight);
	$image->save(realpath($newFile->path), $newFile->name, $image->getType());
	$newFile->imageSize = $image->getSize();
	$newFile->size = filesize(realpath($newFile->path).DIRECTORY_SEPARATOR.$newFile->name);
}

$newFile->uploadBy = isset($Account->id) ? $Account->id : 0;
$newFile->uploadDateTime = date("Y-m-d H:i:s");
$newFile->reference = isset($reference) ? $reference : '';
$newFile->refId = isset($refId) ? intval($refId) : null;


/*
* SAVE TO DATABASE
*
*/
if($type) {
	// if we have a type set (such as 'profilePic', 'label', 'background',...)
	// don't save it to the files-table, but save it in referencetabe (=itemType->dbTable + type) itself.
	$data = Array();
	$data[$type] = $newFile->name;

	// first get the old file-name so that we can delete the old file:
	$db->where($configItemTypes[$itemType]['dbid'],$refId);
	$row = $db->getOne($configItemTypes[$itemType]['dbtable'],array($type));
	$oldFilename = $row[$type];
	if($oldFilename && file_exists(realpath($newFile->path).DIRECTORY_SEPARATOR.$oldFilename)) {
		unlink(realpath($newFile->path).DIRECTORY_SEPARATOR.$oldFilename);
	}

	$db->where($configItemTypes[$itemType]['dbid'],$refId);
	$db->update($configItemTypes[$itemType]['dbtable'], $data);
	unset($myFile['tmp-name']); // we don't want to send the tmp name and the file structure to the user, do we?
	$success = '{"success": {';
	$success .=	' "file": '.json_encode($newFile).',  ';
	#$success .=	' "sourcefile": '.json_encode($myFile).' ';
	$success .= '} }';
	echo $success;

} else {

	$dbData = (array) $newFile; // cast to Array to be suitable for DB-Class

	$id = $db->insert($db_table, $dbData);

	if($id) {
		unset($myFile['tmp-name']); // we don't want to send the tmp name and the file structure to the user, do we?
		$newFile->id = $id;
		$success = '{"success": {';
		$success .=	' "file": '.json_encode($newFile).',  ';
		$success .=	' "sourcefile": '.json_encode($myFile).' ';
		$success .= '} }';
		echo $success;
	} else {
		$e = Array("msg"=>'Database Error', "code"=>5);
		array_push($fulManager->errors, $e);
		echo '{"errors": ' . json_encode($fulManager->errors) . '}';
	}

	// jetzt müssen wir noch im ref-table das file setzen (defined in setas im call vom component)
	if($setas && isset($configs)) {
		$data = Array (
			$_POST['setas'] => $id
		);
		$db->where ($configs['dbid'], $refId);
		$db->update ($configs['dbtable'], $data);
	}
}