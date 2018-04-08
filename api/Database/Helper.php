<?php
/**
*	Class Helper
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
*	Class Helper
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
	private $processIndexes = true;

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

	/**
	 * checks differences between db-definition an database 
	 * and updates database if diffs are found and execution is switched on
	 * @param  \Jeff\Api\Environment  $ENV          Instance of Environment
	 * @param  boolean $execute      	If the update should actually be executed
	 * @param  array  $requestArray 	the array of the request
	 *                               	it's possible to do a api/dbupdate/showDbDefinition/tableName
	 *                               	that's what we need this for...
	 * @return void                		directly echos the info
	 */
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
		require_once($ENV->dirs->vendor."jeffstagedoor/Api/api/Account.php");
		$Account = new \Jeff\Api\Models\Account($this->db, $ENV, $this->errorHandler, null);
		$models[] = $Account;
		

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

	/**
	 * takes the dbDefinition of a model and transfers that to proper classes Table, Column, Key
	 * @param  string $tableName    name of the current table
	 * @param  array $dbDefinition the table definition as described in model
	 * @param  string $primaryKey   name of the primary key
	 * @return Table               Instance of Table, containing Column[]
	 */
	private function _buildTableDefinition($tableName, $dbDefinition, $primaryKey) {
		$table = new Table($tableName);
		foreach ($dbDefinition as $column) {
			$table->addColumn(new Column($column));
		}
		$table->setPrimaryKey($primaryKey);
		return $table;
	}

	/**
	 * does the actual check if there are differences between a db-table and the found definitions
	 * @param  \Jeff\Api\Environment $ENV             Instance of Environment
	 * @param  string $tableName        name of the db-table
	 * @param  Table $tableDefinition   Instance of Table, containing all the info about current dbDefinition
	 * @param  array $requestArray      the array of the request
	 *                               	it's possible to do a api/dbupdate/showDbDefinition/tableName
	 *                               	that's what we need this for...
	 * @return void                     will do all the html output inline
	 */
	private function _checkDbIsTheSame($ENV, $tableName, $tableDefinition, $requestArray/*, $primaryKey*/) {

			$result = $this->db->rawQuery("SHOW FULL TABLES LIKE '".$tableName."'");
			if(count($result)>0) {
				// TABLE EXISTS -> check for possible updates
				echo "\n\n<br><br>Table <b>'{$tableName}'</b> <span style='color: #00CC00;'>exists</span>"; //, <i>checking for updates:</i>";
				$tableSnapshot = $this->getTableSnapshot($tableName);

				foreach($tableDefinition->getColumns() as $column) {
						echo "\n<br> &nbsp;&nbsp;&nbsp;&nbsp;".$column->getName();
						$snapshotColumn = $tableSnapshot->findColumn($column->getName());
						$mismatch = false;
						if($snapshotColumn) {
							echo " - <span style='color: #00CC00;'>exists</span> ";
							if($snapshotColumn->getType() != $column->getType()) {
								echo "- <b>TYPES MISMATCH</b>";
								echo "in db: ".$snapshotColumn->getType().", in definition: ".$column->getType()."<br>";
								$mismatch=true;
							}
							if($snapshotColumn->getLength() != $column->getLength()) {
								echo "- <b>LENGTH MISMATCH</b>";
								echo "in db: ".$snapshotColumn->getLength().", in definition: ".$column->getLength()."<br>";
								$mismatch=true;
							}
							if($snapshotColumn->getHasNull() != $column->getHasNull()) {
								echo "- <b>HASNULL MISMATCH:</b> ";
								echo "in db: ".$snapshotColumn->getHasNull().", in definition: ".$column->getHasNull()."<br>";
								$mismatch=true;
							}
							/* OLD NULL-VERSION
							if(isset($column[3]) && $snapshotColumn->getHasNull() != $column[3]) {
								echo "- <b>HASNULL MISMATCH (and set)</b>";
								$mismatch=true;
							} elseif(!isset($column[3])) {
								if($snapshotColumn->getHasNull()===true) {
								echo "- <b>HASNULL MISMATCH</b>";
								$mismatch=true;
								}
							}
							*/

							if($snapshotColumn->getDefault() != $column->getDefault()) {
								echo "- <b>DEFAULT MISMATCH</b> ";
								echo "in db: ".$field->getDefault().", in definition: ".$column->getDefault()."<br>";
								$mismatch=true;
							}
							/* original DEFAULT-VERSION
																				// default as NULL    OR  default as 					'NULL'
							if(isset($column[4]) && $field->getDefault() !== $column[4] && (is_null($field->getDefault()) && strtoupper($column[4])!=='NULL') ) {
								echo "- <b>DEFAULT MISMATCH</b>";
								$mismatch=true;
							} elseif(!isset($column[4])) {
								if($field->getDefault()!==NULL || $field->getDefault()==='') {
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
						echo $this->_extractDbDefinition($tableSnapshot);
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

	/**
	 * gets a current snapshot of a database table
	 * @param  string $tableName name of the table
	 * @return Table             a Table object
	 */
	public function getTableSnapshot($tableName) {
		$result = $this->db->rawQuery("DESCRIBE `".$tableName."`");
		$tableSnapshot = new Table() 

		foreach ($result as $key => $value) {
			$column = new Column(
				$value['Field'], 
				$this->_getColumnType($value['Type']), 
				$this->_getLength($value['Type']), 
				$this->_getNull($value['Null']),
				$this->_getDefault($value['Default']), 
				$this->_getExtra($value['Extra']);
			);
			
			$tableSnapshot->addColumn($column);
		}
		return $tableSnapshot;
	}

	/**
	 * finds and returns the indexes in given db table
	 * @param  string $tableName name of the table
	 * @return array            an array of indexes
	 */
	private function _getIndexes($tableName) {
		$result = $this->db->rawQuery("SHOW INDEX FROM `".$tableName."`");
		return $result;
	}

	/**
	 * alias for _getIndexes($tableName)
	 * @param  string $tableName name of the table
	 * @return array            an array of indexes
	 */
	private function _getKeys($tableName) {
		return $this->_getIndexes($tableName);
	}

	private function _extractDbDefinition($tableInfo) {
		$array = [];
		foreach ($tableInfo as $key => $field) {
			$fieldDefinition = [];
			$fieldDefinition[0] = $field->name;
			$fieldDefinition[1] = $field->getType();
			$fieldDefinition[2] = $field->getLength();
			$fieldDefinition[3] = $field->getHasNull();
			$fieldDefinition[4] = $field->getDefault();
			$fieldDefinition[5] = $field->getExtra();
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

	/**
	 * generates sql to change a column description and executes
	 * @param  string $tableName name of the table
	 * @param  Column $column    Instance of column
	 * @return void
	 */
	private function _alterTable($tableName, $column) {
		$s = "ALTER TABLE `$tableName` CHANGE `{$column->getName()}` ";
		#$s .= $this->_Column($column);
		$s .= $column->getSql();
		$this->_showQuery($s);
		$this->_dbExecute($s);
	}

	/** 
	 * generates sql to create a column via "ALTER TABLE ADD...".
	 * Uses Column::getSql()
	 * @param string $tableName name of the table
	 * @param Column $column    Instance of Column
	 * @return void
	 */
	private function _addColumn($tableName, $column) {
		$s = "ALTER TABLE `$tableName` ADD ";
		#$s .= $this->_Column($column);
		$s .= $column->getSql();
		echo "<br>\n<pre>".$s."</pre>";
		$this->_dbExecute($s);
	}

	/**
	 * just echoes the given query in <pre> tags
	 * @param  string $sql the query to show
	 * @return void
	 */
	private function _showQuery($sql) {
		echo "<div style='margin-left: 50px;'><pre>$sql</pre></div>";
	}

	/**
	 * executes the given query if $this->execute is true
	 * @param  string $sql the query
	 * @return void
	 */
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