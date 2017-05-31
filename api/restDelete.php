<?php
/**
*	Class RestDelete
*	
*	@author Jeff Frohner
*	@copyright Copyright (c) 2015
*	@license   private
*	@version   1.0
*
**/
namespace Jeff\Api;

// 
// DELETE
// 
function rest_delete($request, $data=NULL) 
{
	global $db, $Account, $ENV, $err, $log;

	// that switch is not need here til now, left only for special cases
	// switch($request[0]) {

	// }


	/*
	*	reference-tables
	*	like user2production, artist2track, ...
	*/
	$references = explode("2", $request[0]);
	if(count($references)===2) {
		$modelLeft = $references[0];	// always singular
		$modelRight = $references[1]; // always plural
		$modelRightSingular = substr($modelRight, 0, strlen($modelRight)-1);
		$singularRequest = substr($request[0], 0, strlen($request[0])-1);

		// the class to delete these items from is always the "bigger" one, the right one
		// user2prduction can be deleted in Model-Class Production
		// by the method deleteMany2Many(id, child-model)
		require_once($ENV->dirs->phpRoot . $ENV->dirs->models . ucfirst($modelRight) . ".php");
		$className = "\\" . __NAMESPACE__ . "\\Models\\".ucfirst($modelRight);
		$model = new $className($db);

		if(isset($request[1])) {
			$id = $request[1];
			// get item from db for log!
			$item = $model->getMany2Many($id, $modelLeft);
			#var_dump($item);
			$logData = new \stdClass();
			$logData->{$singularRequest} = $item;
			$refId = $item[$modelRightSingular];
			// delete the item			
			$item = $model->deleteMany2Many($request[1], $modelLeft);
			// get the rest of the related items:
			$items = $model->getMany2Many($refId, $modelLeft, $modelRightSingular);
			ApiHelper::postItems($model, $items, $request[0]);
			ApiHelper::writeLog($request[0], $logData, 'delete');


		} else {
			echo "err, trying to delete an relational item, but no id was found";
		}

	} else {

		/* and here's the default for all usual data
		*
		*/
		try {
			require_once($ENV->dirs->phpRoot . $ENV->dirs->models.$request[0].".php");
			$className = "\\" . __NAMESPACE__ . "\\Models\\" . $request[0];
			$model  = new $className($db);
			$id = $request[1];
			// get item from db for log and cast it into an object of type: 
			// data: { modelName: {id: 1, ....}}
			$item = $model->get($id);
			$data = new \stdClass();
			$data->{$model->modelName} = $item;
			// delete the item
			$success = $model->delete($id);
			// TODO: delete related child items via triggers in db

			// if the model is a sortable, update sort of the related items here
			// tried it via a trigger, that won't work (cant update the same table twice); only solution in db would be a stored procedure.
			// I don't know how to do that, so I'll do it here for now:
			if($model->isSortable) {
				$sql = "Update ".$model->getDbTable()." set sort = sort-1 where sort>".$item['sort']." and ".$model->sortBy."=".$item[$model->sortBy];
				$db->rawQuery($sql);
				// get the left over items to send back to frontend:
				$items = $model->getAll();
				ApiHelper::postItems($model, $items);
			} else {
				echo "{}";
			}
			ApiHelper::writeLog($request[0], $data, 'delete');
		} catch (Exception $e) {
			echo $e;
			exit;
		}
	}

}