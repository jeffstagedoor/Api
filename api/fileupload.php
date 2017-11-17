<?php
/**
*	Class FileUpload
*	
*	@author Jeff Frohner
*	@copyright Copyright (c) 2015
*	@license   private
*	@version   1.0.0
*
**/

namespace Jeff\Api;


Class FileUpload {
	protected $db = NULL;
	protected $fileConfig;
	protected $ENV = NULL;
	protected $errorHandler = NULL;
	public $modelName = "File";

	protected $dbTable = "file";
	public $dbDefinition = Array(
			array ('id', 'int', '11', false, NULL, 'auto_increment'),
			array ('label', 'varchar', '50', true, NULL),
			array ('filename', 'varchar', '100', true, NULL),
			array ('path', 'varchar', '100', true, NULL),
			array ('uploadDate', 'timestamp', null, false, 'CURRENT_TIMESTAMP'),
			array ('user', 'int', '11', true, NULL),
		);
	public $dbPrimaryKey = 'id';


	/** CONSTRUCTOR
	*   just get the passed classes right.
	*
	*/
	public function __construct($db, $ENV, $errorHandler) {
		$this->db = $db;
		$this->ENV = $ENV;
		$this->errorHandler=$errorHandler;
	
		if(!$this->errorHandler) { $this->errorHandler = new ErrorHandler(); }
		if (!file_exists($this->ENV->dirs->appRoot."FileConfig.php")) {
			$this->errorHandler->add(new Error(ErrorHandler::FILE_NO_CONFIG));
			$this->errorHandler->sendErrors();
			$readyToWrite = false;
		} else {
			include_once($this->ENV->dirs->appRoot."FileConfig.php");
			$this->fileConfig = new \FileConfig();
		}
	}


	/** Basic API upload - Method
	*
	*
	*/
	public function upload($data, $Account) {
		$data = $this->_sanitizeData($data);
		if(isset($_FILES[$this->fileConfig::BASE_FILE_PARAM_NAME])) {
			$myFile = $_FILES[$this->fileConfig::BASE_FILE_PARAM_NAME];
		} else {
			$myFile = null;
		}
		require_once('FileManager.php');
		$fileManager = new FileManager($myFile, $this->fileConfig);

		if(!$fileManager->isFileOk() ) {
		// File is not Ok -> respond with errors as json
			echo '{"errors": ' . json_encode($fileManager->errors) . '}';
			exit;
		}
		// set properties
		$fsaveProperties = new \stdClass();
		$fsaveProperties->type=$data->type;
		$fsaveProperties->itemType=$data->itemType;
		$fsaveProperties->setas = $data->setas;
		$fsaveProperties->refId = $data->refId;

		$configItemTypes = $this->fileConfig->getItemTypes();

		if($data->type==='tmp') {
			// in that case we only need to save that file/image and return it's path so that it can be displayed to be processed further
			$fsaveProperties->addonPath = "tmp";
			$tmpFile = $fileManager->saveFile($myFile, $fsaveProperties);
			$tmpFile->path = 'tmp';
			$tmpFile->type = 'tmp';
			return $tmpFile;
			exit;
		}

		if($data->itemType) {
			$fsaveProperties->addonType = ($this->fileConfig::FILENAME_ADDON_TYPE) ? $data->itemType : null;
			$reference = isset($configItemTypes[$data->itemType]['reference']) ? $configItemTypes[$data->itemType]['reference'] : '';
			$fsaveProperties->addonPath = ($this->fileConfig::INCLUDE_ITEM_TYPE_TO_FILEPATH) ? $data->itemType : null;
			$fsaveProperties->addonPath = ($this->fileConfig::INCLUDE_TYPE_TO_FILEPATH) ? $fsaveProperties->addonPath.DIRECTORY_SEPARATOR.$data->type : null;
		} 

		if($data->refId) {
			if($this->fileConfig::FILENAME_ADDON_ID) $fsaveProperties->addonId = $data->refId;
			if($this->fileConfig::FILENAME_ADDON_LABEL && isset($t) && isset($configItemTypes[$t]['labelField'])) {
				// trying to get the specified label (in configItemTypes) from DB
				$configs = $configItemTypes[$t];
				$this->db->where($configs['dbIdField'], intval($data->refId) );
				$result = $this->db->get($configs['dbTable']);
				if($result) {
					$fsaveProperties->addonLabel = $result[0][$configs['labelField']];
				}
			}
		}
		// SAVING AND MOVING THE FILE
		// var_dump($fsaveProperties);
		$newFile = $fileManager->saveFile($myFile, $fsaveProperties);
		if(!$newFile) {
			$this->errorHandler->throwOne(62);
			exit;
		}
		// var_dump($newFile);

		// Now CROP an IMAGE if this was set
		if($data->crop->doCrop && $fileManager->isImage()) {
			require_once('Image.php');
			$image = new Image(realpath($newFile->path).DIRECTORY_SEPARATOR.$newFile->name);
			$image->crop($data->crop->x, $data->crop->y, $data->crop->width, $data->crop->height);
			$image->save(realpath($newFile->path), $newFile->name, $image->getType());
			$newFile->imageSize = $image->getSize();
			$newFile->size = filesize(realpath($newFile->path).DIRECTORY_SEPARATOR.$newFile->name);
		}

		// if a maxWidth or maxHeight was passed resize the image
		if( ($data->maxWidth || $data->maxHeight) && $fileManager->isImage()) {
			require_once('Image.php');
			$image = new Image(realpath($newFile->path).DIRECTORY_SEPARATOR.$newFile->name);
			$image->resizeMax($data->maxWidth, $data->maxHeight);
			$image->save(realpath($newFile->path), $newFile->name, $image->getType());
			$newFile->imageSize = $image->getSize();
			$newFile->size = filesize(realpath($newFile->path).DIRECTORY_SEPARATOR.$newFile->name);
		}



		/*
		* SAVE TO DATABASE
		*
		*/
		$newFile->uploadBy = isset($Account->id) ? $Account->id : 0;
		$newFile->uploadDateTime = date("Y-m-d H:i:s");
		$newFile->reference = isset($data->reference) ? $data->reference : '';
		$newFile->refId = isset($data->refId) ? intval($data->refId) : null;

		// echo "Data:\n";
		// var_dump($data);
		// echo "newFile:\n";
		// var_dump($newFile);
		$configItemTypes = \FileConfig::getItemTypes();
		if(!isset($configItemTypes[$data->itemType])) {
			$this->errorHandler->add(Array("File Save Error", "No Config for item '$data->itemType' found.",true, ErrorHandler::CRITICAL_EMAIL));
			$this->errorHandler->add(60);
			$this->errorHandler->sendApiErrors();
			$this->errorHandler->sendErrors();
			exit;
		}
		$config = $configItemTypes[$data->itemType];

		if($data->type) {
			// if we have a type set (such as 'profilePic', 'label', 'background',...)
			// don't save it to the files-table, but save it in referencetabe (=itemType->dbTable + type) itself.
			$dbData = Array();
			$dbData[$data->type] = $newFile->name;
			// first get the old file-name so that we can delete the old file:
			$this->db->where($config['dbIdField'],$data->refId);
			$row = $this->db->getOne($config['dbTable'],array($data->type));
			$oldFilename = $row[$data->type];
			if($oldFilename && file_exists(realpath($newFile->path).DIRECTORY_SEPARATOR.$oldFilename)) {
				unlink(realpath($newFile->path).DIRECTORY_SEPARATOR.$oldFilename);
			}

			$this->db->where($config['dbIdField'],$data->refId);

			$updateSuccess = $this->db->update($config['dbTable'], $dbData);
			if($updateSuccess) {
				unset($myFile['tmp-name']); // we don't want to send the tmp name and the file structure to the user, do we?
				$response = new \stdClass();
				$response->success = new \stdClass();
				$response->success->file = $newFile;
				ApiHelper::sendResponse(200, json_encode($response));
			} else {
				$this->errorHandler->add("FileUpload DBSave Error", "Could not update refTable '{$config['dbTable']}' with id $data->refId", true, ErrorHandler::CRITICAL_EMAIL);
				$this->errorHandler->add(60);
				$this->errorHandler->sendApiErrors();
				$this->errorHandler->sendErrors();
				exit;
			}


		} else {

			$dbData = (array) $newFile; // cast to Array to be suitable for DB-Class

			$id = $this->db->insert(\FileConfig::DB_TABLE, $dbData);

			if($id) {
				unset($myFile['tmp-name']); // we don't want to send the tmp name and the file structure to the user, do we?
				$newFile->id = $id;
				$response = new \stdClass();
				$response->success = new \stdClass();
				$response->success->file = $newFile;
				ApiHelper::sendResponse(200, json_encode($response));
			} else {
				$this->errorHandler->throwOne(ErrorHandler::DB_ERROR);
				exit;
			}

			// jetzt müssen wir noch im ref-table das file setzen (defined in setas im call vom component)
			if($data->setas && isset($configs)) {
				$dbData = Array (
					$data->setas => $data->id
				);
				$this->db->where ($config['dbIdField'], $data->refId);
				$updateSuccess = $this->db->update ($config['dbTable'], $dbData);
				if(!$updateSuccess) {
					$this->errorHandler->throwOne("FileUpload DBSave Error", "Could not update refTable '{$config['dbTable']}' with id $data->refId", true, ErrorHandler::CRITICAL_EMAIL);
				}
			}
		}




	}

	/**
	*	_sanitizeData
	*	a method, that checks whether specific values are set and sets them default if not.
	* 	then we don't need the isset() all the way through...
	*/
	private function _sanitizeData($data) {
		// getting possible params from data
		$data->type = isset($data->type) ? $data->type : null;
		$data->itemType = isset($data->itemType) ? $data->itemType : null;
		$data->refId = isset($data->refId) ? $data->refId : null;
		$data->setas = (isset($data->setas) && $data->setas!='undefined') ? $data->setas : null;
		// getting crop-values:
		$data->crop = new \stdClass();
		$data->crop->doCrop = (isset($data->crop) && $data->crop==='true') ? true : false;
		$data->crop->x = (isset($data->cropX)) ? $data->cropX : null;
		$data->crop->y = (isset($data->cropY)) ? $data->cropY : null;
		$data->crop->width = (isset($data->cropWidth)) ? $data->cropWidth : null;
		$data->crop->height = (isset($data->cropHeight)) ? $data->cropHeight : null;
		// not used til now:
		$data->crop->rotate = (isset($data->cropRotate)) ? $data->cropRotate : null;
		$data->crop->scaleX = (isset($data->cropScaleX)) ? $data->cropScaleX : null;
		$data->crop->scaleY = (isset($data->cropScaleY)) ? $data->cropScaleY : null;

		$data->maxWidth = (isset($data->maxWidth) && $data->maxWidth!='undefined') ? $data->maxWidth : null;
		$data->maxHeight = (isset($data->maxHeight) && $data->maxHeight!='undefined') ? $data->maxHeight : null;
		return $data;
	}



	/** Basic API Write to DBLog - Method, will be overridden by special logs
	*
	*
	*/
	// public function write($user, $type, $itemName, $data) {
	// 	if(!$this->readyToWrite) { 
	// 		return null;
	// 	}
	// 	// first Prepare from custom LogConfig
	// 	// so let's see if we have a configuration for the given item:
	// 	if (isset( $this->logConfig->{$itemName} )) {
	// 		$for = $this->extractDataFromConfig($this->logConfig->{$itemName}->for, $data);
	// 		$meta = $this->extractDataFromConfig($this->logConfig->{$itemName}->meta, $data);
	// 	} else {
	// 		// fallback to default
	// 		$logForConfig = new LogDefaultFor(NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL);
	// 		$logMetaConfig = new LogDefaultMeta(Array($itemName,"id"),NULL,NULL,NULL,NULL);
	// 		$for = $this->extractDataFromConfig($logForConfig, $data);
	// 		$meta = $this->extractDataFromConfig($logMetaConfig, $data);
	// 	}

	// 	$data = new \stdClass();
	// 	$data->user = $user;
	// 	$data->type = $type;
	// 	$data->item = $itemName;


	// 	$dbData = array_merge((Array) $data, (Array) $for, (Array) $meta);
	// 	// var_dump($this->logConfig);
	// 	// var_dump($dbData);
	// 	// var_dump($this->db)
	// 	$id = $this->db->insert($this->dbTable, $dbData);
	// 	return $id;		
	// }


	private function extractDataFromConfig($fileConfig, $data) {
		echo "is this needed here? (Class FileUpload->extractDataFromConfig() Line 85)";
		foreach ($fileConfig as $key => $value) {
			if(is_array($value)) {
				// extract specified values from $data
				if(isset($data->{$value[0]}[$value[1]])) {
					$v = $data->{$value[0]}[$value[1]];
				} else {
					$v = null;
				}
				$fileConfig->{$key} = $v;
			}
		}
		return $fileConfig;		
	}


	public function getDbTable() {
		return $this->dbTable;
	}
}





// These are the default classes that shall be used by LogConfig.php
Class FileDefaultConfig {
	// These Parameters can be overridden in php_root/FileConfig.php
	protected $maxNrOfFiles = 1; 				// for now I go with only 1 file
	const BASE_FILE_PARAM_NAME = 'file'; 	// the param-name of the file as it comes from html-form, as fetched in JeffFileUpload.js->startUpload();

	const FILENAME_ADDON_ID = true;
	const FILENAME_ADDON_TYPE = true;
	const FILENAME_ADDON_LABEL = true;
	const INCLUDE_ITEM_TYPE_TO_FILEPATH = true;
	const INCLUDE_TYPE_TO_FILEPATH = true;

	// these are the defaults (in FileManager), that can be overridden that way:
	protected $allowedExtensions = Array(
			'jpg', 'jpeg', 'png',
			'mp3', 'aac', 'mp4', 'mpeg',
			'pdf', 'txt', 'doc', 'docx', 'xls', 'xlsx',
			'sib'
	);
	protected $allowedMimeTypes = Array(
		'text/plain',
		'image/jpeg',
		'application/octet-stream', // f.e. xls
		'application/pdf', // f.e. xls
	);

	public $targetFolder = "..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."files".DIRECTORY_SEPARATOR;

	public static $itemTypes = '[]'; // as json string, because it's easier to write

	protected $maxMegaByte = 5;
	// protected $maxFileSize = $maxMegaByte*1024*1024; // in Byte

	const DB_TABLE = "file";

	public static function values() {
		$values = new \stdClass();
		return $values;
	}

	public static function getItemTypes() {
		return json_decode(static::$itemTypes, true);
	}

	public static function getPath() {
		return dirname(__FILE__).DIRECTORY_SEPARATOR.static::PATH.DIRECTORY_SEPARATOR;;
	}

	public static function getDbTable() {
		return static::DB_TABLE;
	}
}

// Class LogDefaultFor {
// 	public $A;
// 	public $ARights;
// 	public $B;
// 	public $BRights;
// 	public $C;
// 	public $CRights;
// 	public $D;
// 	public $DRights;

// 	function __construct($A, $ARights, $B, $BRights, $C, $CRights, $D, $DRights) {
// 		$this->A 		= $A;
// 		$this->ARights 	= $ARights;
// 		$this->B 		= $B;
// 		$this->BRights 	= $BRights;
// 		$this->C 		= $C;
// 		$this->CRights 	= $CRights;
// 		$this->D 		= $D;
// 		$this->DRights 	= $DRights;
// 	}
// }

// Class LogDefaultMeta {
// 	public $Meta1;
// 	public $Meta2;
// 	public $Meta3;
// 	public $Meta4;
// 	public $Meta5;

// 	function __construct($A=null, $B=null, $C=null, $D=null, $E=null) {
// 		$this->Meta1 = $A;
// 		$this->Meta2 = $B;
// 		$this->Meta3 = $C;
// 		$this->Meta4 = $D;
// 		$this->Meta5 = $E;
// 	}
// }