<?php
/**
*	Classes DBHelper
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
namespace Jeff\Api;


/**
*	Classes DBHelper
*	
*	Helper functions for API and it's Database
*
*	@author Jeff Frohner
*	@copyright Copyright (c) 2017
*	@license   private
*	@version   1.0
*
*/
Class dbHelper {
	private $db;
	private $errorHandler;
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
				$className = "\\" . __NAMESPACE__ . "\\Models\\" . $modelName;
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
		
		require_once($ENV->dirs->vendor."jeffstagedoor/Api/api/TasksPrototype.php");
		require_once($ENV->dirs->appRoot."Tasks.php");
		$Task = new \Jeff\Api\Tasks($this->db, $ENV, $this->errorHandler);
		$models[] = $Task;

		// Accounts/Users should be an extended model in consuming App
		// require_once($ENV->dirs->vendor."jeffstagedoor/Api/api/Account.php");
		// $Account = new \Jeff\Api\Models\Account($this->db, $ENV, $this->errorHandler, null);
		// $models[] = $Account;
		
		$models[] = $LogLogin;

		if(count($models)===0) {
			echo "no Models found in folder AppRoot/models";
		}

		foreach($models as $model) {
			if(!isset($model->dbDefinition)) {
				echo "<h4 style='color: #CC0000;'>ERR: There is no dbDefinition defined for '".$model->modelName."'</h4>";
				continue;
			}

			$tableName = $model->getDbTable();
			$dbDefinition = $model->dbDefinition;
			$primaryKey = $model->dbPrimaryKey;
			
			$this->_checkDbIsTheSame($ENV, $tableName, $dbDefinition, $requestArray, $primaryKey);

			if(isset($model->hasMany)) {
				foreach ($model->hasMany as $key => $def) {
					$tableName = $key;
					if(isset($def['db'])) {
						$dbDefinition = $def['db'];
						$primaryKey = $def['primaryKey'];
						$this->_checkDbIsTheSame($ENV, $tableName, $dbDefinition, $requestArray, $primaryKey);
					}
				}
			}

		}


	}

	private function _checkDbIsTheSame($ENV, $tableName, $dbDefinition, $requestArray, $primaryKey) {

			$result = $this->db->rawQuery("SHOW FULL TABLES LIKE '".$tableName."'");
			if(count($result)>0) {
				// TABLE EXISTS -> check for possible updates
				echo "\n\n<br><br>Table <b>'{$tableName}'</b> <span style='color: #00CC00;'>exists</span>"; //, <i>checking for updates:</i>";

				foreach($dbDefinition as $column) {
						$tableInfo = $this->getTableInfo($tableName);


						echo "\n<br> &nbsp;&nbsp;&nbsp;&nbsp;".$column[0];
						$field = $this->_findColumn($tableInfo, $column[0]);
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
								// echo "field->default: -".($field->default==='')."- column[4]:";
								// echo isset($column[4]) ? "-".($column[4]==='')."-" : "not set ";
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
				$table = new dbTable($tableName);
				foreach ($dbDefinition as $column) {
					$table->addColumn(new dbColumn($column));
				}

				if(isset($primaryKey)) {
					$table->setPrimaryKey($primaryKey);
				}
				if(isset($model->dbKeys)) {
					foreach ($model->dbKeys as $dbKey) {
						$table->addKey(new dbKey($dbKey[0], $dbKey[1]));
					}
				}
				$sql = $table->getSql();
				$this->_showQuery($sql);
				$this->_dbExecute($sql);
			}



	}

	public function getTableInfo($tableName) { // used: true
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
		$s = "ALTER TABLE `$tableName` CHANGE `{$column[0]}` ";
		$s .= $this->_Column($column);
		$this->_showQuery($s);
		$this->_dbExecute($s);
	}


	private function _addColumn($tableName, $column) {
		$s = "ALTER TABLE `$tableName` ADD ";
		$s .= $this->_Column($column);
		echo "<br>\n<pre>".$s."</pre>";
		$this->_dbExecute($s);
	}

	private function _Column($column) {
		$s="`".$column[0]."` ".$column[1];
		if(isset($column[2]) && $column[2]) {
			$s.= '('.$column[2].')';
		}
		if(isset($column[3]) && !$column[3]) {
			$s.= ' NOT NULL';
		}
		if(isset($column[4]) && $column[4]!==NULL) {
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

/**
* several helper classes to create an sql statement to create tables
*
*
*/
Class dbTable {
	private $name;
	private $columns;
	private $primaryKey;
	private $keys;

	public function __construct($name) {
		$this->name = $name;
		$this->columns = Array();
		$this->keys = Array();
	}

	public function addColumn($column) {
		$this->columns[] = $column;
		return $this;
	}

	public function addKey($key) {
		$this->keys[] = $key;
		return $this;
	}

	public function setPrimaryKey($primaryKey) {
		$this->primaryKey = $primaryKey;
		return $this;
	}

	public function getSql() {
		$sql = "CREATE TABLE IF NOT EXISTS `$this->name` (\n";
		$s=Array();
		foreach ($this->columns as $key => $column) {
			$s[] = $column->getSql();
		}
		if($this->primaryKey) {
			$s[] = "PRIMARY KEY (`".$this->primaryKey."`)";
		}
		foreach ($this->keys as $key) {
			$s[] = $key->getSql();
		}
		return $sql.implode(",\n", $s)."\n)";
	}

	public function getColumns() {
		return $this->columns;
	}


}

Class dbColumn {
	private $name;
	private $type;
	private $length;
	private $hasNull;
	private $default;
	private $extra;

	/*
	*	takes one column-definition array
	*	OR $name, $type, $length, $hasNull, $default, $extra
	*/
	public function __construct() {
			$numargs = func_num_args();
			if($numargs===1 && is_array(func_get_arg(0))) {
				// we have an array with the column definition
				$column = func_get_arg(0);
			} elseif ($numargs===0) {
				throw new Exception("Error: Ivalid argument count in Class dbField. Must be an array or at least 2 column descriptors: name, type", 1);
			} else {
				$column = func_get_args();	
			}
			$this->name = $column[0];
			$this->type = $column[1];
			$this->length = isset($column[2]) ? $column[2] : null;
			$this->hasNull = isset($column[3]) ? $column[3] : false;
			$this->default = isset($column[4]) ? $column[4] : null;
			$this->extra = isset($column[5]) ? $column[5] : null;
	}

	public function getSql() {
		$s = '`'.$this->name.'`'.' '.$this->type;
		if($this->length) {
			$s.= '('.$this->length.')';
		}
		if(!$this->hasNull) {
			$s.= ' NOT NULL';
		}
		if($this->default!=null) {
			if(strtoupper($this->default)==='NULL') {
				$s.= ' DEFAULT NULL'; 

			} else {
				if (strtoupper($this->default)==='CURRENT_TIMESTAMP' && strtoupper($this->type)==='TIMESTAMP') {
						$s.= ' DEFAULT CURRENT_TIMESTAMP'; 
				} 
				// elseif(strtoupper($this->default)==='CURRENT_DATE' && strtoupper($this->type)==='DATE') {
				// 		$s.= ' DEFAULT CURRENT_DATE'; 
				// }
				else { 
					$s.= ' DEFAULT \''.$this->default.'\'';
				}
			}
		}
		if($this->extra) {
			$s.= ' '.$this->extra;
		}

		return $s;
	}
}

Class dbKey {
	private $name;
	private $columns;

	public function __construct($name, Array $columns) {
		$this->name = $name;
		$this->columns = $columns;
	}

	public function getSql() {
		$s = "KEY `$this->name` (";
		$k = Array();
		foreach ($this->columns as $key => $value) {
			$k[] = "`$value`";
		}
		return $s.implode(',', $k).")";
	}
}