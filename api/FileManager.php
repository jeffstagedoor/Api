<?php
#########################
#
# Class FileUploadManager
#
# FileUploadManager.php
#
#
#
# copy Jeff Frohner 2015
#
#########################

namespace Jeff\Api;

Class FileManager {
	public $errors = Array();
	public $checkFileSize = true;
	public $checkFileExtention = true;
	public $checkFileMimeType = false;

	// default-properties
	public $allowedExtensions = Array(
		'jpg', 'jpeg', 'png',
		'mp3', 'aac', 'mp4', 'mpeg',
		'pdf', 'txt', 'doc', 'docx', 'xls', 'xlsx',
		'sib'
	);

	public $allowedMimeTypes =  Array(
		'text/plain',
		'image/jpeg',
		'image/png',
		'image/gif',
		'application/octet-stream', // f.e. xls
	);

	public $maxFileSize = 5242880;  // = 5MB

	public $dbTable = 'files';
	public $targetFolder = "../../files";

	// contructor with optional properties to be set
	public function __construct($myFile, $properties = null) {
		$this->file = $myFile;
		if(!is_null($properties)) {
			foreach ($properties as $property => $value) {
				$this->{$property} = $value;
			}
		}
		#$this->targetFolder = $this->targetFolder;
	}

	public function hasErrors() {
		if(sizeof($this->errors)>0) {
			return true;
		} else {
			return false;
		}
	}


	# 
	# checks all the available tests if File passes them
	# @return true if passed, false if any error
	#
	public function isFileOk($myFile=null) {
		if(is_null($myFile)) {
			$myFile = $this->file;
		}
		#echo "tmp_name (in isFileOk) : ".$myFile['tmp_name'];
		if(is_null($myFile)) {
			$e = Array("msg"=>'no valid File received', "code"=>1);
			array_push($this->errors,$e);
		}
		if(!$this->isExtOk($myFile) && $this->checkFileExtention) {
			$e = Array("msg"=>'Filetype not allowed', "code"=>2);
			array_push($this->errors,$e);	
		}
		if(!$this->isMimeTypeOk($myFile) && $this->checkFileMimeType) {
			$e = Array("msg"=>'Mime-Type not allowed', "code"=>3);
			array_push($this->errors,$e);	
		}
		if(!$this->isSizeOk($myFile) && $this->checkFileSize) {
			$e = Array("msg"=>'Maximum Filesize exeeded', "code"=>4);
			array_push($this->errors,$e);
		}
		if($this->hasErrors()) {
			return false;
		} else {
			return true;
		}
	}

	# 
	# checks if the file is an image
	# @return true or false
	#
	public function isImage($myFile=null) {
		if(is_null($myFile)) {
			$myFile = $this->file;
		}
		list($media, $mime) = explode("/",$myFile['type']);
		return ($media==='image') ? true : false;
	}


	# 
	# saves/moves the uploaded file
	# @return file-data on success, false on error
	#
	public function saveFile($sourceFile, $prefs=null) {

		// if a preference-object was given set those:
		if(isset($prefs->targetName)) {
			$targetName=$prefs->targetName . "." . $this->getExtension($sourceFile['name']);
		} else {
			$targetName=$this->makeFilename($this->getExtension($sourceFile['name']), $prefs);
		}
		if(isset($prefs->addonPath)) {
			$this->targetFolder = $this->targetFolder.$prefs->addonPath.DIRECTORY_SEPARATOR;
		}

		$data = new \stdClass();

		$data->name = $targetName;
		$data->filetype = $this->getExtension($sourceFile['name']);
		$data->size = filesize($sourceFile['tmp_name']);
		$data->path = $this->targetFolder;
		#echo "\ntargetFolder in fulManager saveFile(): ".$this->targetFolder."\n";
		$success = $this->moveFile($sourceFile, $targetName, $this->targetFolder);
		if($success) {
			return $data;
		} else {
			return false;
		}
	}

	public function moveFile($sourceFile, $targetName, $targetFolder) {
		if(!is_dir($targetFolder)) {
			#echo "\ncreating dir:".$targetFolder."\n";
			mkdir($targetFolder, 0755, true);
		}
		#echo "\nmoving uploaded file: ".$sourceFile['tmp_name']."\nto: ".$targetFolder.$targetName."\n";
		return move_uploaded_file($sourceFile['tmp_name'], $targetFolder.$targetName);
	}

	public function makeFilename($ext, $prefs=null) {
		$x = '';
		if($prefs) {
			if(isset($prefs->addonId)) {
				$x.=$prefs->addonId.'_';
			}
			if(isset($prefs->addonType)) {
				$x.=$prefs->addonType.'_';
			}
			if(isset($prefs->addonLabel)) {
				$x.=$prefs->addonLabel.'_';
			}
		}
		$x.=uniqid().'.'.$ext;
		#echo "new Filename: ".$x."\n";
		return $x;
	}

	public function makePath($prefs=null) {

	}


	private function isExtOk($myFile) {
		$ext = $this->getExtension($myFile['name']);
		if(in_array($ext, $this->allowedExtensions)) {
			return true;
		} else {
			return false;
		}
	}
	
	private function isMimeTypeOk($myFile) {
		if(in_array($myFile['type'], $this->allowedMimeTypes)) {
			return true;
		} else {
			return false;
		}
	}

	private function isSizeOk($myFile) {
		if(filesize($myFile['tmp_name'])< $this->maxFileSize) {
			return true;
		} else {
			return false;
		}	
	}

	private function getExtension($fname) {
		return pathinfo($fname, PATHINFO_EXTENSION);
	}

	public function extractZip($zipName, $target) {
		global $ENV;
		$zip = new ZipArchive;
		if ($zip->open($zipName) === TRUE) {
			$success = $zip->extractTo($ENV->dirs->files.$target.'/');
			$zip->close();
			return $success;
		} else {
			return false;
		}
	}

	public function readZip($zipName) {
		$zip = zip_open($zipName);
		if ($zip) {
			while ($zip_entry = zip_read($zip)) {
				echo "Name:               " . zip_entry_name($zip_entry) . "\n";
				echo "Actual Filesize:    " . zip_entry_filesize($zip_entry) . "\n";
				echo "Compressed Size:    " . zip_entry_compressedsize($zip_entry) . "\n";
				echo "Compression Method: " . zip_entry_compressionmethod($zip_entry) . "\n";

				if (zip_entry_open($zip, $zip_entry, "r")) {
					echo "File Contents:\n";
					$buf = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
					echo "$buf\n";

					zip_entry_close($zip_entry);
				}
				echo "\n";
			}
			zip_close($zip);
		}
	}

}

?>