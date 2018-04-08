<?php
/**
*	Classes Helper
*	
*	Helper functions for API and it's Database
*
*
*	@author Jeff Frohner
*	@copyright Copyright (c) 2017
*	@license   private
*	@version   1.0
*
*/
namespace Jeff\Api\Database;

require_once('Table.php');
require_once('Column.php');
require_once('Key.php');

/**
*	Classes Helper
*	
*	Helper functions for API and it's Database
*
*	@author Jeff Frohner
*	@copyright Copyright (c) 2017
*	@license   private
*	@version   1.0
*
*/
Class Helper {
	/** @var \MySliDb                 Instance of database class */
	private $db;
	/** @var \Jeff\Api\ErrorHandler   Instance of ErrorHandler */
	private $errorHandler;
	/** @var boolean just a switch to enable/disable indexes */
	private $processIndexes = false;

	/**
	 * Constructor
	 *
	 * Just assigns passed in instances to private vars
	 * 
	 * @param \MySliDb     $db           Instance of database class
	 * @param ErrorHandler $errorHandler Instance of ErrorHandler
	 */
	public function __construct($db, $errorHandler) {
		$this->db = $db;
		$this->errorHandler = $errorHandler;
	}

	public function update($ENV, $execute=false, $requestArray) {
		echo "<html><body>";
		echo $execute ? "<h5 style='color: #0d0;'>execution is switched ON.</h5>" : "<h5 style='color: #bbb;'>execution is switched OFF.</h5>";
		$this->execute = $execute;
		echo "- getting all Models<br>\n";

		$dh  = opendir($ENV->dirs->models);
		while (false !== ($filename = readdir($dh))) {
			if($filename!='.' && $filename!='..') {
				require_once($ENV->dirs->models.$filename);
				$path_parts = pathinfo($ENV->dirs->models.$filename);
				$modelName = $path_parts['filename'];
				echo $modelName."<br>\n";
				$className = "\\Jeff\\Api\\Models\\" . $modelName;
				$model = new $className($this->db, $ENV, $this->errorHandler, null);
				$models[] = $model;
			}
		}
		// special "Models":


		require_once($ENV->dirs->vendor."jeffstagedoor/Api/api/Log/Log.php");
		$Log = new \Jeff\Api\Log\Log($this->db, $ENV, $this->errorHandler);
		$models[] = $Log;


		require_once($ENV->dirs->vendor."jeffstagedoor/Api/api/Log/LogLogin.php");
		$LogLogin = new \Jeff\Api\Log\LogLogin($this->db, $ENV, $this->errorHandler);
		$models[] = $LogLogin;
		
		require_once($ENV->dirs->vendor."jeffstagedoor/Api/api/TasksPrototype.php");
		require_once($ENV->dirs->appRoot."Tasks.php");
		$Task = new \Jeff\Api\Tasks($this->db, $ENV, $this->errorHandler);
		$models[] = $Task;

		// Accounts/Users should be an extended model in consuming App
		// require_once($ENV->dirs->vendor."jeffstagedoor/Api/api/Account.php");
		// $Account = new \Jeff\Api\Models\Account($this->db, $ENV, $this->errorHandler, null);
		// $models[] = $Account;
		

		if(count($models)===0) {
			echo "no Models found in folder AppRoot/models";
		}

		foreach($models as $model) {
			if(!isset($model->dbDefinition)) {
				echo "<h4 style='color: #CC0000;'>ERR: There is no dbDefinition defined for '".$model->modelName."'</h4>";
				continue;
			}

			$tableName = $model->getDbTable();
			$tableDefinition = $this->_buildTableDefinition($tableName, $model->dbDefinition, $model->dbPrimaryKey);
			
			$this->_checkDbIsTheSame($ENV, $tableName, $tableDefinition, $requestArray/*, $model->dbPrimaryKey*/);

			if(isset($model->hasMany)) {
				foreach ($model->hasMany as $key => $def) {
					$tableName = $key;
					if(isset($def['db'])) {
						// var_dump($def);
						$tableDefinition = $this->_buildTableDefinition($tableName, $def['db'], $def['primaryKey']);
						$this->_checkDbIsTheSame($ENV, $tableName, $tableDefinition, $requestArray/*, $primaryKey*/);
					}
				}
			}
		}

		echo "</body></html>";
	}

	private function _buildTableDefinition($tableName, $dbDefinition, $primaryKey) {
		$table = new Table($tableName);
		foreach ($dbDefinition as $column) {
			$table->addColumn(new Column($column));
		}
		$table->setPrimaryKey($primaryKey);
		return $table;
	}

	private function _checkDbIsTheSame($ENV, $tableName, $tableDefinition, $requestArray/*, $primaryKey*/) {

			$result = $this->db->rawQuery("SHOW FULL TABLES LIKE '".$tableName."'");
			if(count($result)>0) {
				// TABLE EXISTS -> check for possible updates
				echo "\n\n<br><br>Table <b>'{$tableName}'</b> <span style='color: #00CC00;'>exists</span>"; //, <i>checking for updates:</i>";

				#$tableDefinition = buildTableDefinition($tableName, $dbDefinition, $primaryKey); 
				$tableInfo = $this->getTableInfo($tableName);

				#var_dump($tableDefinition);
				foreach($tableDefinition->getColumns() as $column) {
					#var_dump($column);


						echo "\n<br> &nbsp;&nbsp;&nbsp;&nbsp;".$column->name;
						$field = $this->_findColumn($tableInfo, $column->name);
						$mismatch = false;
						if($field) {
							echo " - <span style='color: #00CC00;'>exists</span> ";
							if($field->type != $column->type) {
								echo "- <b>TYPES MISMATCH</b>";
								echo "in db: ".$field->type.", in definition: ".$column->type."<br>";
								$mismatch=true;
							}
							if($field->length != $column->length) {
								echo "- <b>LENGTH MISMATCH</b>";
								echo "in db: ".$field->length.", in definition: ".$column->length."<br>";
								$mismatch=true;
							}
							if($field->hasNull != $column->hasNull) {
								echo "- <b>HASNULL MISMATCH:</b> ";
								echo "in db: ".$field->hasNull.", in definition: ".$column->hasNull."<br>";
								$mismatch=true;
							}
							/* OLD NULL-VERSION
							if(isset($column[3]) && $field->hasNull != $column[3]) {
								echo "- <b>HASNULL MISMATCH (and set)</b>";
								$mismatch=true;
							} elseif(!isset($column[3])) {
								if($field->hasNull===true) {
								echo "- <b>HASNULL MISMATCH</b>";
								$mismatch=true;
								}
							}
							*/

							if($field->default != $column->default) {
								echo "- <b>DEFAULT MISMATCH</b> ";
								echo "in db: ".$field->default.", in definition: ".$column->default."<br>";
								$mismatch=true;
							}
							/* original DEFAULT-VERSION
																				// default as NULL    OR  default as 					'NULL'
							if(isset($column[4]) && $field->default !== $column[4] && (is_null($field->default) && strtoupper($column[4])!=='NULL') ) {
								echo "- <b>DEFAULT MISMATCH</b>";
								$mismatch=true;
							} elseif(!isset($column[4])) {
								if($field->default!==NULL || $field->default==='') {
								echo "- <b>DEFAULT MISMATCH</b>";
								$mismatch=true;
								}
							}
							*/
							if($mismatch) {
								// ALTER TABLE
								$this->_alterTable($tableName, $column);
							} else {
								echo "<span style='color: #00CC00;'>and matches</span> ";
							}
						} else {
								echo " - IS MISSING IN TABLE<br>";
								$this->_addColumn($tableName, $column);
						}
				}


				// KEYS / INDEXES:

				if($this->processIndexes) {
					$indexes = $this->_getIndexes($tableName);
					#var_dump($indexes);
					$foundPrimaryInDB = false;
					foreach ($indexes as $index) {
						if($index['Key_name']==='PRIMARY') {
							if(isset($primaryKey) && $primaryKey===$index['Column_name']) {
								// Primary Key matches
							} else {
								echo "<br>- <b>PRIMARY KEY MISMATCH</b>";
								if(is_null($primaryKey)) {
									echo "\n<br>Primary key exists in DB (on '{$index['Column_name']}') , but is NOT set in Model";
								}
							}
							$foundPrimaryInDB=true;
						}
					}
					if(!is_null($primaryKey) && !$foundPrimaryInDB) {
						echo "- <b>PRIMARY KEY MISMATCH</b>";
						echo "\n<br>Primary key is defined in Model (on '{$model->dbPrimaryKey}') , but is NOT defined in DB";
					}
				} // if $processIndexes


				// got the INFO from Database, now let's compare what we've got in definitions:
				if(isset($requestArray[1]) && $requestArray[1]==='showDbDefinition' && isset($requestArray[2]) && $requestArray[2]===$tableName) {
						echo "<pre>";
						echo $this->_extractDbDefinition($tableInfo);
						echo "</pre>";
				} else {
					echo "<br><a href=\"{$ENV->urls->baseUrl}{$ENV->urls->apiUrl}dbupdate/showDbDefinition/$tableName/\">showDbDefinition</a><br>";
				}
			} else {
				// TABLE DOESN'T EXIST -> create it
				echo "\n\n<br><br>Table <b>'{$tableName}'</b> <span style='color: #d00;'>does NOT exist</span>, let's create it:<br>\n";
				$table = new Table($tableName);
				foreach ($dbDefinition as $column) {
					$table->addColumn(new Column($column));
				}

				if(isset($primaryKey)) {
					$table->setPrimaryKey($primaryKey);
				}
				if(isset($model->dbKeys)) {
					foreach ($model->dbKeys as $dbKey) {
						$table->addKey(new Key($dbKey[0], $dbKey[1]));
					}
				}
				$sql = $table->getSql();
				$this->_showQuery($sql);
				$this->_dbExecute($sql);
			}



	}

	public function getTableInfo($tableName) {
		$result = $this->db->rawQuery("DESCRIBE `".$tableName."`");

		// $tableInfo=$result;
		$tableInfo = array();
		// print_r($result);
		foreach ($result as $key => $value) {
			// print_r($value);
			$info = new \stdClass();
			$info->name = $value['Field'];
			$info->type = $this->_getColumnType($value['Type']);
			$info->length = $this->_getLength($value['Type']);
			$info->hasNull = $this->_getNull($value['Null']);
			$info->default = $this->_getDefault($value['Default']);
			$info->extra = $this->_getExtra($value['Extra']);
			$tableInfo[] = $info;
		}
		return $tableInfo;
	}


	private function _getIndexes($tableName) {
		$result = $this->db->rawQuery("SHOW INDEX FROM `".$tableName."`");
		return $result;
		// print_r($result);
	}

	private function _extractDbDefinition($tableInfo) {
		$array = [];
		foreach ($tableInfo as $key => $field) {
			$fieldDefinition = [];
			$fieldDefinition[0] = $field->name;
			$fieldDefinition[1] = $field->type;
			$fieldDefinition[2] = $field->length;
			$fieldDefinition[3] = $field->hasNull;
			$fieldDefinition[4] = $field->default;
			$fieldDefinition[5] = $field->extra;
			$array[] = $fieldDefinition;
		}
		// $array = $tableInfo;
		return var_export($array, true);
	}

	private function _findColumn($tableInfo, $column) {
		foreach($tableInfo as $ti) {
			if($ti->name==$column) {
				return $ti;
			}
		}
		return false;
	}

	private function _alterTable($tableName, $column) {
		$s = "ALTER TABLE `$tableName` CHANGE `{$column->name}` ";
		#$s .= $this->_Column($column);
		$s .= $column->getSql();
		$this->_showQuery($s);
		$this->_dbExecute($s);
	}


	private function _addColumn($tableName, $column) {
		$s = "ALTER TABLE `$tableName` ADD ";
		#$s .= $this->_Column($column);
		$s .= $column->getSql();
		echo "<br>\n<pre>".$s."</pre>";
		$this->_dbExecute($s);
	}

	private function _showQuery($sql) {
		echo "<div style='margin-left: 50px;'><pre>$sql</pre></div>";
	}

	private function _dbExecute($sql) {
		if($this->execute) {
			$result = $this->db->rawQuery($sql);
		}
		if($this->db->getLastErrno() === 0) {
			echo $this->execute ? "<i>..executed</i><br>" : "";
		} else {
			echo "\n<br><b style='color: #d00;'>a database error occured: {$this->db->getLastErrno()}</b><br><i style='background: #ffa; padding: 3px;'>{$this->db->getLastError()}</i><br>";
		}
	}

	/**
	 * gets column length from given column info
	 * @param  string $info column-info
	 */
	private function _getLength($info) {
		$pattern = '/\({1}([\d\W]*)\){1}/';
		preg_match($pattern, $info, $matches);
		return isset($matches[1]) ? $matches[1] : NULL;
	}

	/**
	 * gets column type from given column info
	 * @param  string $info column-info
	 */
	private function _getColumnType($info) {
		$pattern = '/([\w]*)(\([\d\W]*\))*/';
		preg_match($pattern, $info, $matches);
		return isset($matches[1]) ? $matches[1] : NULL;
	}

	/**
	 * gets if column isNull from given column info
	 * @param  string $info column-info
	 */
	private function _getNull($info) {
		if($info==='NO') {
			return false;
		} else {
			return true;
		}
	}

	/**
	 * gets column default from given column info
	 * @param  string $info column-info
	 */
	private function _getDefault($info) {
		if($info>='') {
			return $info;
		} else {
			return NULL;
		}
	}


	/**
	 * gets column extra info from given column info
	 * @param  string $info column-info
	 */
	private function _getExtra($info) {
		if($info>='') {
			return $info;	
		} else {
			return NULL;
		}
	}

}