<?php
/**
*	Class RestPost
*	
*	@author Jeff Frohner
*	@copyright Copyright (c) 2015
*	@license   private
*	@version   1.2
*
**/
namespace Jeff\Api;

// 
// POST
// 
function rest_post($request, $data=NULL) {
	global $db, $ENV, $Account, $authUser, $err, $log;
	$response = new \stdClass();

	switch($request[0]) {
		case "tasks":
			include_once("tasks.php");
			$response = task($request[1], $data);
			ApiHelper::sendResponse(200, $response);
			exit;
			break;

		case "fileupload":
			include_once('fileupload.php');
			exit;
			break;

		case "signup":
			// Name splitten
			include_once("Names.php");
			$names = Names::Arrange($data->fullName);
			$data->firstName = $names[0];
			$data->middleName = $names[1];
			$data->prefixName = $names[2];
			$data->lastName = $names[3];

			include_once("Models/Account.php");
			$user = new Models\Account($db);
			$id = $user->signup($data);
			if($id) {
				$authToken = $user->getAuthById($id);
				$response = "{ \"id\": ".$id.", \"authToken\": \"".$authToken."\" }";
				ApiHelper::sendResponse(200, $response);
				exit;
			} else {
				ApiHelper::sendResponse(200,"{ \"errors\": " .json_encode($user->errors)."}");
				exit;
			}
			break;
	}

	/* referenceTables: user2workgroup, user2production, artist2track,...
	*	$type is the singular name of the reference-Model ('production'), but the post comes like this: 'user2productions'
	*/
	$references = explode("2", $request[0]);
	if(count($references)===2) {
		$modelLeft = $references[0];	// always singular
		$modelRight = $references[1]; // always plural
		$singularRequest = substr($request[0], 0, strlen($request[0])-1);

		require_once($ENV->dirs->phpRoot . $ENV->dirs->models . ucfirst($modelRight).".php");
		// $className = "\\".__NAMESPACE__."\\Models\\".ucfirst($modelRight);
		$className = "\\" . __NAMESPACE__ . "\\Models\\" . ucfirst($modelRight);
		

		$model = new $className($db);

		$data->{$modelLeft.'2'.$model->modelName}['modBy'] = $Account->id;
		unset($data->{$modelLeft.'2'.$model->modelName}['modDate']);
		$id = $model->addMany2Many($modelLeft, $data->{$modelLeft.'2'.$model->modelName});				

		if($id) {
			// send back the actual record
			$item = $model->getMany2Many($id, $modelLeft, 'id');
			ApiHelper::postItem($model, $item, $modelLeft.'2'.$model->modelName);
			$data->{$modelLeft.'2'.$model->modelName}['id'] = $id;
			ApiHelper::writeLog($request[0], $data, $modelLeft.'2'.$model->modelName.'add');
			exit;
		} else {
			// here I should send an error....

		}



	} else {

		// include_once($ENV->dirs->phpRoot . $ENV->dirs->models . ucfirst($request[0]).".php");
		$className = ApiHelper::getModel($request[0], $ENV);
		// $className = "\\" . __NAMESPACE__ . "\\Models\\" . ucfirst($request[0]);
		$model = new $className($db);

		if($model->modifiedByField) {
			// default: 'modBy'
			$data->{$model->modelName}[$model->modifiedByField] = $Account->id;
		}
		unset($data->{$model->modelName}['modDate']);


		try {
			/* if we need to add multiple items
			*/
			if(isset($request[1]) && $request[1]==='multiple') {
				$items = $model->addMultiple($data->{$model->modelName}, $data->multipleParams);
				ApiHelper::postItems($model, $items);
				$data->{$model->modelName}['count'] = count($items);
				ApiHelper::writeLog($request[0], $data, 'createMultiple');
				exit;
			}
			/* and here's the default for a simple single post
			*/
			var_dump($data);
			$id = $model->add($data->{$model->modelName});
			if($err->hasErrors()) {
				$err->sendApiErrors();
				exit;
			}
			$x = $model->getOneById($id);
			$data->{$model->modelName}['id'] = $id;

			ApiHelper::postItem($model, $x, $model->modelName);
			ApiHelper::writeLog($request[0], $data, 'create');
			exit;
		} catch (Exception $e) {
			echo $e;
			exit;	
		}

		exit;
	}

}