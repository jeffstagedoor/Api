<?php
/**
*	Class ApiGet
*	
*	@author Jeff Frohner
*	@copyright Copyright (c) 2015
*	@license   private
*	@version   1.0
*
**/
namespace Jeff\Api;

// require_once("../config.php");

// 
// GET
// 

Class ApiGet 
{
	private $db;
	private $Account;
	private $request;
	private $ENV;
	private $items;

	function __construct($request, $data, $ENV, $db, $errorHandler) {
		// global $ENV;
		$this->request = $request;
		$this->data = $data;
		$this->ENV = $ENV;
		$this->db = $db;
		$this->errorHandler = $errorHandler;
	}

	public function getItems() {
		switch ($this->request->type) {
			case Api::REQUEST_TYPE_REFERENCE: 
				break;
			case Api::REQUEST_TYPE_COALESCE:
				$this->items = $this->request->model->getCoalesce($this->data->ids);
				break;
			case Api::REQUEST_TYPE_QUERY:
				$filter = $this->_getFilter();
				$this->items = $this->request->model->getAll($filter);
				break;
			case Api::REQUEST_TYPE_NORMAL:
				if(isset($this->request->id)) {
					$this->items = $this->request->model->getOneById($this->request->id);
				} elseif(isset($this->request->special)) {
					switch ($this->request->special) {
						case "search":
							$this->items = $this->request->model->search($this->data);
							break;
						case "count":
							$count = $this->request->model->count($this->data);
							$responseObj = new \stdClass();
							$responseObj->count = $count;
							ApiHelper::sendMeta($responseObj);
							exit;
							break;
					}
				} else {
					// get all we can get (restrictions are made in Model.php and in beforeGetAll()-hook)
					$this->items = $this->request->model->getAll();
				}	
				break;
		}
		return $this->items;
	}

	public function getSpecial() {
		// echo "GET REQUEST_TYPE_SPECIAL: ".$this->request->special;
		switch ($this->request->special) {
			case "getFolder":
				$this->_getFolder();
				exit;
			case "getFile":
				$this->errorHandler->throwOne(Array("API-Error", "getFile not yet implemented."));
				exit;
			case "getImage":
				$this->_getImage();
				exit;
		}
	}

	private function _getFilter() {
		// it's a query-call (in Ember: `this.store.query('item', {filter: [{"key":value}], gte: [{date: '2016-04-28'}])` ) 
		// since this format is not very handy, let's restructure that.
		$data = $this->data;
		$newFilter = Array();

		if(isset($data->filter) && is_array($data->filter)) {
			foreach ($data->filter as $key => $value) {
				$newFilter[] = Array("key"=>$key, "value"=>$value, "comp"=>"=");
			}
		}
		if(isset($data->gt) && is_array($data->gt)) {
			foreach ($data->gt as $key => $value) {
				$newFilter[] = Array("key"=>$key, "value"=>$value, "comp"=>">");
			}
		}
		if(isset($data->gte) && is_array($data->gte)) {
			foreach ($data->gte as $key => $value) {
				$newFilter[] = Array("key"=>$key, "value"=>$value, "comp"=>">=");
			}
		}
		if(isset($data->lt) && is_array($data->lt)) {
			foreach ($data->lt as $key => $value) {
				$newFilter[] = Array("key"=>$key, "value"=>$value, "comp"=>"<");
			}
		}					
		if(isset($data->lte) && is_array($data->lte)) {
			foreach ($data->lte as $key => $value) {
				$newFilter[] = Array("key"=>$key, "value"=>$value, "comp"=>"<=");
			}
		}
		return $newFilter;
	}


	private function _getFolder() {
		if(isset($this->request->requestArray[1])) {
			$requestedFolder = $this->request->requestArray[1];
		} elseif (isset($this->data->folder)) {
			$requestedFolder = $this->data->folder;
		} else {
			$requestedFolder = null;
		}
		if(!isset($this->ENV->publicFolders->{$requestedFolder})) {
			// publicFolder not set -> send Error Message
			$this->errorHandler->throwOne(Array("API-Error", "Public Folder not defined."));
			exit;
		}
		$publicFolder = $this->ENV->publicFolders->{$request[1]};
		// echo $publicFolder;

		// first check if we have a valid folder:
		if(!is_dir($publicFolder)) {
			$this->errorHandler->throwOne(Array("API-Error", "Folder not found."));
			exit;
		}

		$files = [];
		try {
			$objects = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($publicFolder));
			foreach($objects as $name => $fileInfo){
				if($fileInfo->isDir()) continue;
				if($fileInfo->getBasename()==='.gitignore') continue;
				if($fileInfo->getExtension()==='php') continue;
				$relativepath = str_replace($publicFolder, '', $fileInfo->getPath());
				$relativepath = $relativepath ? substr_replace($relativepath, '', 0,1) .DIRECTORY_SEPARATOR : '';
				$files[] = Array('folder'=>$relativepath, 'filename'=>$fileInfo->getBasename(), 'extension'=>$fileInfo->getExtension(), "size"=>$fileInfo->getSize());
			}
		} 
		catch (Exception $e) {
			echo 'Caught exception: ',  $e->getMessage(), "\n";
		}
		// $response = Array("files" => $files);
		echo json_encode($files);
		
	}


	private function _getImage() {
		if(isset($data->file)) {
			// VERSION 1 (filename directly given):
			$filename = $data->file;
			// the path is ../files/itemType[/type]:
			$path = $ENV->dirs->files;
			if(isset($data->itemType)) $path.=$data->itemType.DIRECTORY_SEPARATOR;
			if(isset($data->type)) $path.=$data->type.DIRECTORY_SEPARATOR;
		} elseif (isset($data->imageId)) {
			// VERSION 2 (filename & path has to be fetched from db-table files)
			// TODO
			$this->errorHandler->throwOne(Array("API-Error", "Image fetching from DB not yet implemented (ApiGet.class.php line 176)"));
			exit;
		} else {
			$this->errorHandler->throwOne(Array("API-Error", "wrong call to getImage. Missing data 'file' or 'imageId'"));
			exit;
		}
		
		require_once('Image.php');
		$image = new Image($path.$filename);
		header('Content-Type: '.$image->getHeader());
		if(isset($data->width) && isset($data->height)) {
			$image->resizeMax($width,$height);
		}
		$image->show();
	}

}