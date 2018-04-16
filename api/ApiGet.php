<?php
/**
*	contains Class ApiGet
*	
*	@author Jeff Frohner
*	@copyright Copyright (c) 2015
*	@license   private
*	@version   1.9.9
*
**/

namespace Jeff\Api;
use Jeff\Api\Request\RequestType;

/**
* Class ApiGet
*
* Handles all requests that come via GET
* 
* @author Jeff Frohner
* @copyright Copyright (c) 2015
* @license   private
* @version   1.8.0
*
**/
Class ApiGet 
{
	/** @var \MySqliDb Instance of database class */
	private $db;
	/** @var Models\Account Instance of Account class */
	private $account;
	/** @var object the request Object */
	private $request;
	/** @var array the requested items */
	private $items;

	/**
	 * The Constructor.
	 * Only sets the passed in instances/classes to private vars
	 * @param object         $request      The requst object
	 * @param \MySqliDb      $db           Instance of Database class
	 * @param Models\Account $account      Instance of Account
	 */
	function __construct($request, $db, $account) {
		$this->request = $request;
		$this->data = $request->data;
		$this->db = $db;
		$this->account = $account;
		$this->items = new \stdClass();
	}



	/**
	 * Calls the matching methods in (extending) Model-class.
	 *
	 * Depending on request type this prepares for and calls either
	 * - getOneById (a REQUEST_TYPE_NORMAL with a given id as second param)
	 * - getAll (a REQUEST_TYPE_NORMAL _without_ a given id as second param)
	 * - getMany2Many (a REQUEST_TYPE_REFERENCE)
	 * - getCoalesque (a REQUEST_TYPE_COALESQUE that looks like this `..api/posts?ids[]=1&ids[]=2&ids[]=3`)
	 * - _getFilter + getAll (a REQUEST_TYPE_QUERY)
	 * 
	 *   There can be special GET requests implemented:
	 *   - search
	 *   - count
	 * 
	 * @return response-object the updated item|items
	 */
	public function getItems() {
		switch ($this->request->type) {
			case RequestType::REFERENCE: 
				$id = isset($this->request->id) ? $this->request->id : null;
				$filter = $this->_getFilter();
				$this->items->{$this->request->params[0]} = $this->request->model->getMany2Many($id, $this->request->modelLeft->modelNamePlural, 'id', $filter); // I might need the by='id' somehwhere..?
				break;
			case RequestType::COALESCE:
				// var_dump($this->request);
				$this->items->{$this->request->model->modelNamePlural} = $this->request->model->getCoalesce($this->data->ids);
				break;
			case RequestType::QUERY:
				$filter = $this->_getFilter();
				$this->items->{$this->request->model->modelNamePlural} = $this->request->model->getAll($filter);
				break;
			case RequestType::NORMAL:
				if(isset($this->request->id)) {
					$this->items->{$this->request->model->modelName} = $this->request->model->getOneById($this->request->id);
					if(is_null($this->items->{$this->request->model->modelName})) {
						ErrorHandler::addSendAllExit(21);
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
			default: 
					echo "no matching Request Type: ".$this->request->type;
		}
		return $this->items;
	}

	public function getSpecial() {
		switch ($this->request->special) {
			case "search":
				if(isset($this->request->params[1])) {
					// search only in one model

					$modelName = $this->request->params[1];
					// get the Model
					if($modelName==='accounts') {
						$model = $this->account;
					} else {
						$modelFile = Environment::$dirs->models . ucfirst($modelName) . ".php";
						if (!file_exists($modelFile)) {
							ErrorHandler::throwOne(Array("Api Error", "Requested recource '{$modelName}' not found/defined.", 400, ErrorHandler::CRITICAL_EMAIL, false));
							exit;
						} else {	
							require_once($modelFile);
							$classNameNamespaced = "\\Jeff\\Api\\Models\\" . ucfirst($modelName);
							$model = new $classNameNamespaced($this->db, $this->account);
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
				require_once(Environment::$dirs->appRoot."Tasks.php");
				$tasks = new \Jeff\Api\Tasks($this->db, $this->account);
				// var_dump($this->request->params);
				
				$taskName = 'not defined';
				if(isset($this->request->params[1])) {
					// der task ist im request zb: task/addUserToWorkgroup (die Daten in postData)
					// check if we have a fitting method defined:
					$taskName = $this->request->params[1];
				} elseif (isset($this->data->task)) {
					// ist der task woanders versteckt
					$taskName = $this->data;
				}
				if(method_exists($tasks, $taskName)){
					$response = $tasks->{$taskName}($this->data, $this->request);
				} elseif ($tasks->getTaskByCode($this->request->params[1])){
					#$response = new \stdClass();
					$response = $tasks->getTaskByCode($this->request->params[1]);
					// $response = $task;
				} elseif($task->getTaskById($this->request->params[1])) {
					#$response = new \stdClass();
					$response = $tasks->getTaskById($this->request->params[1]);
				} else {
					ErrorHandler::throwOne(ErrorHandler::TASK_NOT_DEFINED);
					exit;
				}
				return $response;
				break;
		}
	}


	/**
	 * works through an query call and generated a useable filter
	 *
	 * it's a query-call (in Ember: `this.store.query('item', {filter: [{"key":value}], gte: [{date: '2016-04-28'}])` ) 
	 * since this format is not very handy, let's restructure that.
	 *
	 * @return array the filter, which looks like this:
	 *
	 * ```
	 * [
	 *    Array("key"=>'postDate', "value"=>'2016-04-28', "comp"=>"<"),
	 *    Array("key"=>'title', "value"=>'This is a post title', "comp"=>"="),
	 * ]
	 * ```
	 */
	private function _getFilter() {
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


	/**
	 * gets the content of a (predefined) public folder.
	 * 
	 * These foldes can be defined in {@see \Jeff\Api\Environment} as a simple string[]
	 * A fitting request can be:
	 * - ..api/getFolder/folderName
	 * - ..api/getFolder with a fitting data `{folder: 'folderName'}`
	 * 
	 * @return json assoc array of found files:
	 *              `['folder'=> 'myfiles', 'filename'=> 'document.pdf', 'extension': 'pdf', 'size' => 512]`
	 *              
	 */
	private function _getFolder() {
		if(isset($this->request->params[1])) {
			$requestedFolder = $this->request->params[1];
		} elseif (isset($this->data->folder)) {
			$requestedFolder = $this->data->folder;
		} else {
			$requestedFolder = null;
		}
		if(!isset(Environment::$publicFolders->{$requestedFolder})) {
			// publicFolder not set -> send Error Message
			ErrorHandler::throwOne(Array("API-Error", "Public Folder not defined."));
			exit;
		}
		$publicFolder = Environment::$publicFolders->{$request[1]};
		// echo $publicFolder;

		// first check if we have a valid folder:
		if(!is_dir($publicFolder)) {
			ErrorHandler::throwOne(Array("API-Error", "Folder not found."));
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

	/**
	 * gets and shows an image depending on the requests parameters
	 *
	 * Version 1:
	 * a request with a dataset like this:
	 * {
	 *    file: 'filename.jpg',
	 *    itemType: 'resourceName', // e.g. 'artist', 'account', 'post'
	 *    type: 'logo'              // e.g. 'logo', 'profilePic', 'background'
	 * }
	 *
	 * Version 2:
	 * If the imageName is stored in a db table the request-data should look like this
	 * {
	 *     imageId: 99,
	 *     itemType: 'recourceName',  // maybe not needed
	 * }
	 * 
	 * @return [type] [description]
	 */
	private function _getImage() {
		if(isset($this->data->file)) {
			// VERSION 1 (filename directly given):
			$filename = $this->data->file;
			// the path is ../files/itemType[/type]:
			$path = Environment::$dirs->files;
			if(isset($this->data->itemType)) $path.=$this->data->itemType.DIRECTORY_SEPARATOR;
			if(isset($this->data->type)) $path.=$this->data->type.DIRECTORY_SEPARATOR;
		} elseif (isset($this->data->imageId)) {
			// VERSION 2 (filename & path has to be fetched from db-table files)
			// TODO
			ErrorHandler::throwOne(Array("API-Error", "Image fetching from DB not yet implemented (ApiGet.class.php line 176)"));
			exit;
		} else {
			ErrorHandler::throwOne(Array("API-Error", "wrong call to getImage. Missing data 'file' or 'imageId'"));
			exit;
		}
		// var_dump($this->data);
		// echo $path.$filename;
		require_once('Image.php');
		$image = new Image($path.$filename);
		header('Content-Type: '.$image->getHeader());
		if(isset($data->width) && isset($data->height)) {
			$image->resizeMax($width,$height);
		}
		$image->show();
	}

	/**
	 * gets a requested file.
	 * __not yet implemented__
	 * 
	 * @return recource the file to download?
	 */
	private function _getFile() {
		ErrorHandler::throwOne(Array("API-Error", "getFile not yet implemented."));
	}

}