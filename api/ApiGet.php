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
	private $request;
	private $ENV;
	private $items;
	private $account;

	function __construct($request, $data, $ENV, $db, $errorHandler, $account) {
		// global $ENV;
		$this->request = $request;
		$this->data = $data;
		$this->ENV = $ENV;
		$this->db = $db;
		$this->errorHandler = $errorHandler;
		$this->account = $account;
		$this->items = new \stdClass();
	}

	public function getItems() {
		switch ($this->request->type) {
			case Api::REQUEST_TYPE_REFERENCE: 
				$id = isset($this->request->id) ? $this->request->id : null;
				$filter = $this->_getFilter();
				$this->items->{$this->request->requestArray[0]} = $this->request->model->getMany2Many($id, $this->request->modelLeft->modelNamePlural, 'id', $filter); // I might need the by='id' somehwhere..?
				break;
			case Api::REQUEST_TYPE_COALESCE:
				// var_dump($this->request);
				$this->items->{$this->request->model->modelNamePlural} = $this->request->model->getCoalesce($this->data->ids);
				break;
			case Api::REQUEST_TYPE_QUERY:
				$filter = $this->_getFilter();
				$this->items->{$this->request->model->modelNamePlural} = $this->request->model->getAll($filter);
				break;
			case Api::REQUEST_TYPE_NORMAL:
				if(isset($this->request->id)) {
					$this->items->{$this->request->model->modelName} = $this->request->model->getOneById($this->request->id);
					if(is_null($this->items->{$this->request->model->modelName})) {
						$this->errorHandler->addSendAllExit(21);
						exit;
					}
					
				} elseif(isset($this->request->special)) {
					switch ($this->request->special) {
						case "search":
							#echo "DEPRECATED: use api/search[/modelName](+data) instead of api/modelName/search";
							$result = $this->request->model->search($this->data);
							$response = new \stdClass();
							$response->result = $result;
							return $response;
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
					$this->items->{$this->request->model->modelNamePlural} = $this->request->model->getAll();
				}	
				break;
		}
		return $this->items;
	}

	public function getSpecial() {
		// echo "GET REQUEST_TYPE_SPECIAL: ".$this->request->special;
		switch ($this->request->special) {
			case "search":
				if(isset($this->request->requestArray[1])) {
					// search only in one model

					$modelName = $this->request->requestArray[1];
					// get the Model
					if($modelName==='accounts') {
						$model = $this->account;
					} else {
						$modelFile = $this->ENV->dirs->models . ucfirst($modelName) . ".php";
						if (!file_exists($modelFile)) {
							$this->errorHandler->throwOne(Array("Api Error", "Requested recource '{$modelName}' not found/defined.", 400, ErrorHandler::CRITICAL_EMAIL, false));
							exit;
						} else {	
							require_once($modelFile);
							$classNameNamespaced = "\\Jeff\\Api\\Models\\" . ucfirst($modelName);
							$model = new $classNameNamespaced($this->db, $this->ENV, $this->errorHandler,$this->account);
						}
					}
					$result = $model->search($this->data);
					$response = new \stdClass();
					$response->result = $result;
					return $response;
					#ApiHelper::sendResponse(200, json_encode($response));
				} else {
					// search only in complete db?!?
					echo "complete search not yet implemented";
				}
				break;
				exit;
			case "getFolder":
				$this->_getFolder();
				exit;
			case "getFile":
				$this->_getFile();
				exit;
			case "getImage":
				$this->_getImage();
				exit;
			case "task":
				require_once("TasksPrototype.php");
				require_once($this->ENV->dirs->appRoot."Tasks.php");
				$tasks = new \Jeff\Api\Tasks($this->db, $this->ENV, $this->errorHandler, $this->account);
				// var_dump($this->request->requestArray);
				
				$taskName = 'not defined';
				if(isset($this->request->requestArray[1])) {
					// der task ist im request zb: task/addUserToWorkgroup (die Daten in postData)
					// check if we have a fitting method defined:
					$taskName = $this->request->requestArray[1];
				} elseif (isset($this->data->task)) {
					// ist der task woanders versteckt
					$taskName = $this->data;
				}
				if(method_exists($tasks, $taskName)){
					$response = $tasks->{$taskName}($this->data, $this->request);
				} elseif ($tasks->getTaskByCode($this->request->requestArray[1])){
					#$response = new \stdClass();
					$response = $tasks->getTaskByCode($this->request->requestArray[1]);
					// $response = $task;
				} elseif($task->getTaskById($this->request->requestArray[1])) {
					#$response = new \stdClass();
					$response = $tasks->getTaskById($this->request->requestArray[1]);
				} else {
					$this->errorHandler->throwOne(ErrorHandler::TASK_NOT_DEFINED);
					exit;
				}
				return $response;
				break;
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
		if(isset($this->data->file)) {
			// VERSION 1 (filename directly given):
			$filename = $this->data->file;
			// the path is ../files/itemType[/type]:
			$path = $this->ENV->dirs->files;
			if(isset($this->data->itemType)) $path.=$this->data->itemType.DIRECTORY_SEPARATOR;
			if(isset($this->data->type)) $path.=$this->data->type.DIRECTORY_SEPARATOR;
		} elseif (isset($this->data->imageId)) {
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

	private function _getFile() {
		$this->errorHandler->throwOne(Array("API-Error", "getFile not yet implemented."));
	}

}