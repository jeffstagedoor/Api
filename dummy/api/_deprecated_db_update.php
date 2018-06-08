<?php

namespace Jeff\Api;
use Jeff\Api\Models as Models;


require_once("../config.php");
require_once("../../api/Model.php");
require_once("../../api/DBHelper.php");
require_once("../../vendor/joshcam/mysqli-database-class/MysqliDb.php");
$db = new \MysqliDb($ENV->database);
$dbHelper = new dbHelper($db);

if(isset($_GET['execute'])) {
	$execute = true;
} else {
	$exectue = false;
	echo "<h5>execution is switched OFF.</h5>";
}

$dir = "../models";
$dh  = opendir($dir);
$models = [];
while (false !== ($filename = readdir($dh))) {
	if($filename!='.' && $filename!='..') {


		require_once("../models/".$filename);
		$path_parts = pathinfo("../models/".$filename);
		$modelName = $path_parts['filename'];
		$className = "\\" . __NAMESPACE__ . "\\Models\\" . $modelName;
		$model = new $className($db);
		$models[] = $model;
	}
}
if(count($models)===0) {
	echo "no Models found in folder AppRoot/models";
}
foreach($models as $model) {
	if(!isset($model->dbDefinition)) {
		echo "<h4 style='color: #CC0000;'>ERR: There is no dbDefinition defined for Model '".$model->modelName."'</h4>";
		continue;
	}

	$tableName = $model->getDbTable();
	$result = $db->rawQuery("SHOW FULL TABLES LIKE '".$tableName."'");
	if(count($result)>0) {
		// TABLE EXISTS -> check for possible updates
		echo "\n\n<br><br>Table <b>'{$tableName}'</b> <span style='color: #00CC00;'>exists</span>"; //, <i>checking for updates:</i>";
		$tableInfo = $dbHelper->extractTableInfo($tableName);

		foreach($model->dbDefinition as $column) {
			echo "\n<br> &nbsp;&nbsp;&nbsp;&nbsp;".$column[0];
			$field = findColumn($tableInfo, $column[0]);
			$mismatch = false;
			if($field) {
				echo " - <span style='color: #00CC00;'>exists</span> ";
				if($field->type != $column[1]) {
					echo "- <b>TYPES MISMATCH</b>";
					$mismatch=true;
				}
				if($field->length != $column[2]) {
					echo "- <b>LENGTH MISMATCH</b>";
					$mismatch=true;
				}
				if(isset($column[3]) && $field->hasNull != $column[3]) {
					echo "- <b>HASNULL MISMATCH (and set)</b>";
					$mismatch=true;
				} elseif(!isset($column[3])) {
					if($field->hasNull===true) {
					echo "- <b>HASNULL MISMATCH</b>";
					$mismatch=true;
					}
				}
				if(isset($column[4]) && $field->default != $column[4]) {
					echo "- <b>DEFAULT MISMATCH</b>";
					$mismatch=true;
				} elseif(!isset($column[4])) {
					if($field->default!=NULL || $field->default!='') {
					echo "- <b>DEFAULT MISMATCH</b>";
					$mismatch=true;
					}
				}
				if($mismatch) {
					// ALTER TABLE
					alterTable($tableName, $column);
				} else {
					echo "<span style='color: #00CC00;'>and matches</span> ";
				}



			} else {
				echo " - IS MISSING IN TABLE<br>";
				addColumn($tableName, $column);
			}
		}

		// KEYS / INDEXES:
		$indexes = $dbHelper->getIndexes($model->getDbTable());
		#var_dump($indexes);
		$foundPrimaryInDB = false;
		foreach ($indexes as $index) {
			if($index['Key_name']==='PRIMARY') {
				if(isset($model->dbPrimaryKey) && $model->dbPrimaryKey===$index['Column_name']) {
					// Primary Key matches
				} else {
					echo "<br>- <b>PRIMARY KEY MISMATCH</b>";
					if(!isset($model->dbPrimaryKey)) {
						echo "\n<br>Primary key exists in DB (on '{$index['Column_name']}') , but is NOT set in Model";
					}
				}
				$foundPrimaryInDB=true;
			}
			# code...
		}
		if(isset($model->dbPrimaryKey) && !$foundPrimaryInDB) {
			echo "- <b>PRIMARY KEY MISMATCH</b>";
			echo "\n<br>Primary key is defined in Model (on '{$model->dbPrimaryKey}') , but is NOT defined in DB";
		}


		// got the INFO from Database, now let's compare what we've got in definitions:
	} else {
		// TABLE DOESN'T EXIST -> create it
		echo "\n\n<br><br>Table <b>'{$tableName}'</b> <span style='color: #d00;'>does NOT exist</span>, let's create it:<br>\n";
		$table = new dbTable($tableName);
		foreach ($model->dbDefinition as $column) {
			$table->addColumn(new dbColumn($column));
		}

		if(isset($model->dbPrimaryKey)) {
			$table->setPrimaryKey($model->dbPrimaryKey);
		}
		if(isset($model->dbKeys)) {
			foreach ($model->dbKeys as $dbKey) {
				// var_dump($dbKey[1]);
				$table->addKey(new dbKey($dbKey[0], $dbKey[1]));
			}
		}
		$sql = $table->getSql();
		showQuery($sql);
		dbExecute($sql);
	}

}

function findColumn($tableInfo, $column) {
	foreach($tableInfo as $ti) {
		if($ti->name==$column) {
			return $ti;
		}
	}
	return false;
}

function alterTable($tableName, $column) {
	global $db, $execute;
	$s = "ALTER TABLE `$tableName` CHANGE `{$column[0]}` ";
	$s .= Column($column);
	showQuery($s);
	dbExecute($s);
}


function addColumn($tableName, $column) {
	$s = "ALTER TABLE `$tableName` ADD ";
	$s .= Column($column);
	echo "<br>\n<pre>".$s."</pre>";
	dbExecute($s);
}

function Column($column) {
	$s="`".$column[0]."` ".$column[1];
	if(isset($column[2]) && $column[2]) {
		$s.= '('.$column[2].')';
	}
	if(isset($column[3]) && !$column[3]) {
		$s.= ' NOT NULL';
	}
	if(isset($column[4]) && $column[4]!=NULL) {
		if($column[4]==='CURRENT_TIMESTAMP') {
			$s.= ' DEFAULT CURRENT_TIMESTAMP';
		// } elseif($column[4]==='CURRENT_DATE') {
		// 	$s.= ' DEFAULT CURRENT_DATE';
		} else {
			$s.= ' DEFAULT \''.$column[4].'\'';
		}
	}
	if(isset($column[5])) {
		$s.= ' '.$column[5];
	}
	return $s;
}

function showQuery($sql) {
	echo "<div style='margin-left: 50px;'><pre>$sql</pre></div>";
}

function dbExecute($sql) {
	global $db, $execute;
	if($execute) {
		$result = $db->rawQuery($sql);
	}
	if($db->getLastErrno() === 0) {
		echo $execute ? "<i>..executed</i><br>" : "";
	} else {
		echo "\n<br><b style='color: #d00;'>a database error occured: {$db->getLastErrno()}</b><br><i style='background: #ffa; padding: 3px;'>{$db->getLastError()}</i><br>";
	}
}