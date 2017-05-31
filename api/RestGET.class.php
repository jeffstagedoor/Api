<?php
/**
*	Class RestGet
*	
*	@author Jeff Frohner
*	@copyright Copyright (c) 2015
*	@license   private
*	@version   1.0
*
**/
namespace Jeff\Api;

require_once("../config.php");

// 
// GET
// 

Class RestGet {
	private $db;
	private $Account;
	private $model;

	function __construct($db, $request, $data) {
		global $ENV;
		$this->db = $db;


		// find special cases:
		switch($request[0]) {
			case "accounts":
				// special case, because call does not match Model name, 
				// and Account is not a normal Model, thus not located in Models Folder
				require_once($ENV->dirs->phpRoot . $ENV->dirs->api . "Account.php");
				$obj = new Models\Account($db);
				$item = $obj->getOneById($request[1]);
				ApiHelper::postItem($obj, $item, $obj->modelName, $obj->sideload);
				exit;
			case "getimage":
				$this->getImage($data);
				exit;
			case "getfile":
				$this->getFile($data);
				exit;
			case "folderwalk":
				$this->folderwalk($data);
				exit;
		}



		/*
		*	reference-tables
		*	like user2production, artist2track, ...
		*/
		if($this->isReferences($request)) {
			$references = $this->getReferences($request);
			$this->getReference($references, $data, $request);
		} else {
			/* and here's the default for all usual data
			* following properties can be set in Models:
			*	(Array) maskFields: these fields will be masked with *** via DataMasker when doing a search
			*	(Array) unsetProperties: these fields will be deleted/unset in all get-requests
			*/
			try {
				$this->setModel($request[0]);
		
				if(isset($request[1])) {
					switch ($request[1]) {
						case 'search':
							$this->search($data);
							exit;
						case 'count':
							$this->count($data);
							exit;
						default: 
							$item = $this->model->getOneById($request[1]);
							if($item) {
								ApiHelper::postItem($this->model, $item, $this->model->modelName, $this->model->sideload);
							} else {
								ApiHelper::sendResponse(404, "{}");
							}
							exit;
					}
				
					
				} else if (isset($data->ids)) {
					// it's a coalesce findRecords request
					// data->ids is an array of all the id's that should be returned
					$items = $this->model->getCoalesce($data->ids);

				} else if ($this->isQueryCall($data)) {
					$this->queryCall($data);
				} else {
					// get all we can get (restrictions are made in Model.php and in beforeGetAll()-hook)
					$items = $this->model->getAll();
					ApiHelper::postItems($this->model, $items, $this->model->modelNamePlural);
				}
				// send the result back to user/browser/caller:
				exit;
			} catch (Exception $e) {
				echo $e;
				exit;	
			}
		}



	}

	private function setModel($modelName) {
		global $ENV;
		$modelFile = $ENV->dirs->phpRoot . $ENV->dirs->models . ucfirst($modelName) . ".php";
			
			
		if (!file_exists($modelFile)) {
			throw new \Exception ('Falsy API call. Model '.$modelName. ' does not exist.');
		} else {
			require_once($modelFile); 
		}
		$className = "\\" . __NAMESPACE__ . "\\Models\\" . ucfirst($modelName);
		$this->model = new $className($this->db);
	}

	function getModel($modelName=null) {
		if($modelName) {
			$this->setModel($modelName);
			return $this->model;
		} else {
			if(is_null($this->model)) {
				throw new \Exception('Error in RestGET->getModel: No Model found');
			} else {
				return $this->model;
			}
		}
	}

	function isReferences($request) {
		$references = explode("2", $request[0]);
		if(count($references)===2) {
			return true;
		} else {
			return false;
		}
	}

	function isQueryCall($data) {
		// is it a query-call (in Ember: `this.store.query('item', {filter: {"key":value}, gte: {date: '2016-04-28'})` ) 
		return isset($data->filter) || isset($data->gt) || isset($data->gte) || isset($data->lt) || isset($data->lte);
	}

	function queryCall($data) {
		// it's a query-call (in Ember: `this.store.query('item', {filter: {"key":value}, gte: {date: '2016-04-28'})` ) 
		// since this format is not very handy, let's restructure that.
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
		$data->filter = $newFilter;
		$items = $model->getAll($newFilter);
		ApiHelper::postItems($model, $items, $model->modelNamePlural);
	}

	function getReference($references, $data, $request) {
		$modelLeft = $references[0];	// always singular
		$modelRight = $references[1]; // always plural
		$singularRequest = substr($request[0], 0, strlen($request[0])-1);

		// the class to get these items from is always the "bigger" one, the right one
		// user2prduction can be fetched in Model-Class Production
		// via the method getMany2Many(id, by(id), child-model)
		require_once($ENV->dirs->phpRoot . $ENV->dirs->models . ucfirst($modelRight) . ".php");
		$className = "\\" . __NAMESPACE__ . "\\Models\\" . ucfirst($request[0]);
		$model = new $className($db);

		if(isset($request[1])) {
			$item = $model->getMany2Many($request[1], $modelLeft, 'id');
			if($item) {
				ApiHelper::postItem($model, $item, $singularRequest);
			} else {
				ApiHelper::sendResponse(200, "{}");
			}
		} elseif (isset($data->ids)) {
			$items = $model->getMany2Many($data->ids, $modelLeft, 'id');
			ApiHelper::postItems($model, $items, $request[0]);
		} else {
			echo "err, trying to fetch all items of an relational item";
		}

	}


	/*
	SPECIAL CASES
	*/
	function search($data) {
		$result = $this->model->search($data);
		ApiHelper::sendResponse(200, "{\"result\": ".json_encode($result)." }");
	}

	function count($data) {
		/* THIS IS STILL A SPECIFIC VERSION!!!!!
		// NEED SOME WORK ON THAT
		*/
		$wgArray = Array(1,3);
		$returnObj = new \stdClass();
		$returnObj->countTotal = $model->getCount();
		var_dump($data);
		echo "NEED SOME WORK HERE! (Model->getCount())";
		$returnObj->countWorkgroup = $model->getCount($data->per,$wgArray);
		ApiHelper::sendResponse(200, "{\"meta\": ".json_encode($returnObj)." }");
	}

	function getFile($data) {
		throw new \Exception ('getFile is not yet imlemented');
	}



	function getImage($data) {
		global $ENV;

		if(isset($data->file)) {
			// VERSION 1 (filename directly given):
			$filename = $data->file;
			// the path is ../files/itemType[/type]:
			$path = $ENV->dirs->files;
			if(isset($data->itemType)) $path.=$data->itemType.DIRECTORY_SEPARATOR;
			if(isset($data->type)) $path.=$data->type.DIRECTORY_SEPARATOR;
		} elseif (isset($data->fileId)) {
			// VERSION 2 (filename & path has to be fetched from db-table files)
			// TODO
		} else {
			throw new \Exception ('Falsy API call for getImage');
		}
		
		require_once('Image.php');
		$image = new Image($path.$filename);
		header('Content-Type: '.$image->getHeader());
		if(isset($data->width) && isset($data->height)) {
			$image->resizeMax($width,$height);
		}
		$image->show();
	}

	function folderWalk($request) {
		global $ENV;
		if(!isset($ENV->publicFolders->{$request[1]})) {
			// publicFolder not set -> send Error Message
			$errors[] = "API Error. Public Folder not defined.";
			ApiHelper::sendResponse(400,"{ \"errors\": ".json_encode($errors)."}");
			exit;
		}
		$publicFolder = $ENV->publicFolders->{$request[1]};
		// echo $publicFolder;

		// first check if we have a valid folder:
		if(!is_dir($publicFolder)) {
			$errors[] = "Folder not found";
			echo '{"errors": ' . json_encode($errors) . '}';
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
		
		ApiHelper::sendResponse(200,json_encode($files));
		// echo json_encode($files);
	}

}