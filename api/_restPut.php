<?php
/**
*	Class RestPut
*	
*	@author Jeff Frohner
*	@copyright Copyright (c) 2015
*	@license   private
*	@version   1.0
*
**/
namespace Jeff\Api;

// 
// PUT
// 
function rest_put($request, $data=NULL) {
	global $db, $Account, $authUser, $ENV, $err, $log;
	
	$id = isset($request[1]) ? $request[1] : NULL;

	switch ($request[0]) {
		// first the specific account-updates, that can only be invoked by the actual user (tested via authtoken)
		case "changePassword": // move to tasks??
			require_once($ENV->dirs->phpRoot . $ENV->dirs->models . "Account.php");
			$obj = new Models\User($db);
			$auth = $obj->authenticate($authUser['email'], $data->password);
			if(isset($auth->error)) {
				// if oldPassword doesnt match the saved one, send invalid grant error
				$response->errors=$err->add(92);
				ApiHelper::sendResponse(200,json_encode($response));
				exit;
			} else {
				if($data->passwordNew===$data->passwordConfirm) {
					$obj->changePassword($authUser['id'], $data->passwordNew);
					$response = "{\"success\": [{\"msg\": \"password changed\"}] }";
					ApiHelper::sendResponse(200,$response);
					exit;
				} else {
					$response->errors=$err->add(97);
					ApiHelper::sendResponse(200,json_encode($response));
					exit;					
				}
			}
			exit;
		case "changeName":
			require_once($ENV->dirs->phpRoot . $ENV->dirs->models . "Account.php");
			$obj = new Models\User($db);
			$success = $obj->update($authUser['id'], $data);
			if($success) {
				// LOG-ENTRY
				// FOR: U_ID, U_RIGHTS, WG_ID, WG_RIGHTS, P_ID, P_RIGHTS, A_ID, A_RIGHTS
				$for = $log->makeFor($authUser['id'], Constants::USER_ADMIN, null, null, null, null);
				$meta = $log->makeMeta($authUser['id'], null, null, null, $data->fullName);
				$logId = $log->writeLog($authUser['id'], 'update', 'user', $for, $meta);
				// send response
				$response = "{\"success\": [{\"msg\": \"name changed\"}] }";
				ApiHelper::sendResponse(200,$response);
			} else {
				$response->errors=$err->add(20);
				ApiHelper::sendResponse(200, json_encode($response));
			}
			exit;

		case "sort":
			$model = $data->item;
			require_once($ENV->dirs->phpRoot . $ENV->dirs->models . "{$model}.php");
			$className = "\\".__NAMESPACE__ . "\\Models\\".$model;
			$model = new $className($db);
			if($model->isSortable) {
				// mothod sort returns ALL items in the reference
				$items = $model->sort($data->reference, $data->id, $data->direction, $data->currentSort);
				if($items) {
					ApiHelper::postItems($model, $items, $model->modelNamePlural);
				} else {
					$err->sendApiErrors();
				}
			} else {
				$err->add(33);
				$err->sendApiErrors();
			}
			exit;
	}
	
	// here's the actual standard data-update:


	/*
	*	reference-tables
	*	like user2production, artist2track, ...
	*/
	$references = explode("2", $request[0]);
	if(count($references)===2) {
		$modelLeft = $references[0];	// always singular
		$modelRight = $references[1]; // always plural
		$singularRequest = substr($request[0], 0, strlen($request[0])-1);

		// the class to update these items from is always the "bigger" one, the right one
		// user2prduction can be put in Model-Class Production
		// by the method updateMany2Many(id, child-model, data)
		require_once($ENV->dirs->phpRoot . $ENV->dirs->models . ucfirst($modelRight) . ".php");
		$className = "\\" . __NAMESPACE__ . "\\Models\\".ucfirst($modelRight);
		$model = new $className($db);

		if(isset($request[1])) {
			if($model->modifiedByField) {
				$data->{$singularRequest}[$model->modifiedByField] = $Account->id;
			}
			unset($data->{$singularRequest}['modDate']);
			
			$item = $model->updateMany2Many($request[1], $modelLeft, $data->{$singularRequest});
			$data->{$singularRequest}['id'] = $id;
			$item = $model->getMany2Many($id, $modelLeft, 'id');
			ApiHelper::postItem($model, $item, $singularRequest);
			ApiHelper::writeLog($request[0], $data, 'update');


		} else {
			echo "err, trying to update an relational item, but no id was found";
		}

	} else {
	
		/* and here's the default for all usual data
		*/
		try {
			$className = ApiHelper::getModel($request[0], $ENV);
			$model = new $className($db);
			if($model->modifiedByField) {
				$data->{$singularRequest}[$model->modifiedByField] = $Account->id;
			}
			unset($data->{$model->modelName}['modDate']);	// will be set in/by db itself
			// WORKING HERE !!
			// echo "\nthis is in working stage. Trying to set 'null' to a real NULL in data-array\n";
			// $data->{$model->modelName}['invoiceDate'] = NULL;
			// echo "\n\ninvoiceDate: ".$data->{$model->modelName}['invoiceDate'];
			//
			$model->update($id, $data->{$model->modelName});
	
			$item = $model->getOneById($id);
			ApiHelper::postItem($model, $item, $model->modelName);
			$logData = new \stdClass();
			$logData->{$model->modelName} = $item;
			ApiHelper::writeLog($request[0], $logData, 'update');
			exit;
			
		} catch (Exception $e) {
			echo $e;
			exit;	
		}
	}
}