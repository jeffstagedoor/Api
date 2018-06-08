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

use Jeff\Api\Environment;
use Jeff\Api\ErrorHandler;

require_once('Table.php');
require_once('Column.php');
require_once('Key.php');
require_once('DBTableRepresentation.php');

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
	/** @var boolean just a switch to enable/disable indexes */
	private $processIndexes = false;

	/**
	 * Constructor
	 *
	 * Just assigns passed in instances to private vars
	 * 
	 * @param \MySliDb     $db           Instance of database class
	 */
	public function __construct($db) {
		$this->db = $db;
	}

	/**
	 * checks differences between db-definition an database 
	 * and updates database if diffs are found and execution is switched on
	 * @param  boolean $execute      	If the update should actually be executed
	 * @param  array  $params		 	the array params of the request
	 *                               	it's possible to do a api/dbupdate/showDbDefinition/tableName
	 *                               	that's what we need this for...
	 * @return void                		directly echos the info
	 */
	public function update($execute=false, $params) {
		echo "<html><body>";
		echo $execute ? "<h5 style='color: #0d0;'>execution is switched ON.</h5>" : "<h5 style='color: #bbb;'>execution is switched OFF.</h5>";
		$this->execute = $execute;
		echo "- getting all Models<br>\n";

		$dh  = opendir(Environment::$dirs->models);
		while (false !== ($filename = readdir($dh))) {
			if($filename!='.' && $filename!='..') {
				require_once(Environment::$dirs->models.$filename);
				$path_parts = pathinfo(Environment::$dirs->models.$filename);
				$modelName = $path_parts['filename'];
				echo $modelName."<br>\n";
				$className = "\\Jeff\\Api\\Models\\" . $modelName;
				$model = new $className($this->db, null);
				$models[] = $model;
			}
		}
		// special "Models":


		// require_once(Environment::$dirs->api."Log/Log.php");
		// $Log = new \Jeff\Api\Log\Log($this->db);
		// $models[] = $Log;


		// require_once(Environment::$dirs->api."Log/LogLogin.php");
		// $LogLogin = new \Jeff\Api\Log\LogLogin($this->db);
		// $models[] = $LogLogin;
		
		require_once(Environment::$dirs->api."TasksPrototype.php");
		if(file_exists(Environment::$dirs->appRoot."Tasks.php")) {
			require_once(Environment::$dirs->appRoot."Tasks.php");
			$Task = new \Jeff\Api\Tasks($this->db);
			$models[] = $Task;
		}

		// Accounts/Users should be an extended model in consuming App
		require_once(Environment::$dirs->api."Models/Account.php");
		$Account = new \Jeff\Api\Models\Account($this->db, null);
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
			$tableDefinition = $this->_buildTableDefinition($tableName, $model->dbDefinition, $model->dbPrimaryKey, $model->dbKeys);
			
			$this->_checkDbIsTheSame($tableName, $tableDefinition, $params, $model->dbPrimaryKey);

			if(isset($model->hasMany)) {
				foreach ($model->hasMany as $key => $def) {
					$tableName = $key;
					if(isset($def['db'])) {
						$primaryKey = isset($def['primaryKey']) ? $def['primaryKey'] : NULL;
						$keys = isset($def['keys']) ? $def['keys'] : [];
						$tableDefinition = $this->_buildTableDefinition($tableName, $def['db'], $primaryKey, $keys);
						$this->_checkDbIsTheSame($tableName, $tableDefinition, $params, $primaryKey);
					}
				}
			}
		}

		echo "</body></html>";
	}

	/**
	 * takes the dbDefinition of a model and transfers that to proper classes Table, Column, Key
	 * @param  string  $tableName     name of the current table
	 * @param  array   $dbDefinition  the table definition as described in model
	 * @param  string  $primaryKey    name of the primary key
	 * @param  array   $keys          definition of the keys
	 * @return Table                  Instance of Table, containing Column[]
	 */
	private function _buildTableDefinition($tableName, $dbDefinition, $primaryKey, $keys) {
		$table = new Table($tableName);
		foreach ($dbDefinition as $column) {
			$table->addColumn(new Column($column));
		}
		$table->setPrimaryKey($primaryKey);
		return $table;
	}

	/**
	 * does the actual check if there are differences between a db-table and the found definitions
	 * @param  string $tableName        name of the db-table
	 * @param  Table $tableDefinition   Instance of Table, containing all the info about current dbDefinition
	 * @param  array $params      the array of the request
	 *                               	it's possible to do a api/dbupdate/showDbDefinition/tableName
	 *                               	that's what we need this for...
	 * @return void                     will do all the html output inline
	 */
	private function _checkDbIsTheSame($tableName, $tableDefinition, $params, $primaryKey) {

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
						echo "<br><span style='color: #333;'>PRIMARY KEY: </span>";
						echo "defined: ".$tableDefinition->getPrimaryKey().",";
						echo "found: ".$tableDefinition->getPrimaryKey()." ";
					if($tableDefinition->getPrimaryKey()==$tableSnapshot->getPrimaryKey()) {
						echo "<span style='color: #00CC00;'>matches</span> ";
						
					} else {
						echo "<br>- <b>PRIMARY KEY MISMATCH</b>";
						if(is_null($tableDefinition->getPrimaryKey()) && !is_null($tableSnapshot->getPrimaryKey())) {
							echo "\n<br>Primary key exists in DB (on '{$tableSnapshot->getPrimaryKey()}') , but is NOT set in Model<br>";
						} elseif (!is_null($tableDefinition->getPrimaryKey()) && is_null($tableSnapshot->getPrimaryKey())) {
							echo "\n<br>Primary key is defined in Model (on '{$tableDefinition->getPrimaryKey()}'), but doesn't exists in DB<br>";
						}

						echo "<span style:'color: #f00;'>no sql to change that yet implemented...</span>";
					}
					
					// Keys:
					// var_dump($tableSnapshot->getKeys());
					echo "Keys in database:<br>";
					foreach ($tableSnapshot->getKeys() as $key) {
						echo $key->getName()."<br>";
					}

					// foreach ($indexes as $index) {
					// 	if($index['Key_name']==='PRIMARY') {
					// 		if(isset($primaryKey) && $primaryKey===$index['Column_name']) {
					// 			// Primary Key matches
					// 		} else {
					// 			echo "<br>- <b>PRIMARY KEY MISMATCH</b>";
					// 			if(is_null($primaryKey)) {
					// 				echo "\n<br>Primary key exists in DB (on '{$index['Column_name']}') , but is NOT set in Model";
					// 			}
					// 		}
					// 		$foundPrimaryInDB=true;
					// 	}
					// }
					// if(!is_null($primaryKey) && !$foundPrimaryInDB) {
					// 	echo "- <b>PRIMARY KEY MISMATCH</b>";
					// 	echo "\n<br>Primary key is defined in Model (on '{$model->dbPrimaryKey}') , but is NOT defined in DB";
					// }
				} // if $processIndexes


				// got the INFO from Database, now let's compare what we've got in definitions:
				if(isset($params[1]) && $params[1]==='showDbDefinition' && isset($params[2]) && $params[2]===$tableName) {
						echo "<pre>";
						echo $this->_extractDbDefinition($tableSnapshot);
						echo "</pre>";
				} else {
					echo "<br><a href=\"".Environment::$urls->baseUrl.Environment::$urls->apiUrl."dbupdate/showDbDefinition/$tableName/\">showDbDefinition</a><br>";
				}
			} else {
				// TABLE DOESN'T EXIST -> create it
				echo "\n\n<br><br>Table <b>'{$tableName}'</b> <span style='color: #d00;'>does NOT exist</span>, let's create it:<br>\n";
				$table = new Table($tableName);
				foreach ($tableDefinition->getColumns() as $column) {
					$table->addColumn($column);
				}

				if(isset($primaryKey)) {
					$table->setPrimaryKey($primaryKey);
				}
				if(isset($model->dbKeys)) {
					foreach ($model->dbKeys as $dbKey) {
						$table->addKey(new Key($dbKey[0], $dbKey[1]));
					}
				}
				$sql = $table->getCreateTableSql();
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
		$tableSnapshot = new Table($tableName);
		echo "<pre>";
		// var_dump($result);
		echo "</pre>";
		foreach ($result as $key => $value) {
			$column = new Column(
				$value['Field'], 
				$this->_getColumnType($value['Type']), 
				$this->_getLength($value['Type']), 
				$this->_getNull($value['Null']),
				$this->_getDefault($value['Default']), 
				$this->_getExtra($value['Extra'])
			);
			
			$tableSnapshot->addColumn($column);
			// if($value['Key']=='PRI') {
			// 	$tableSnapshot->setPrimaryKey($value['Field']);
			// }
		}
		$indexInfo = $this->_getIndexes($tableName);
		if(isset($indexInfo->primaryKey)) {
	 		$tableSnapshot->setPrimaryKey($indexInfo->primaryKey);
		}
		foreach ($indexInfo->keys as $key) {
		 		$tableSnapshot->addKey(new Key($key->name, $key));
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
		$oIndexes = new \stdClass();
		$oIndexes->primaryKey = null;
		$oIndexes->keys = [];
		foreach ($result as $key => $indexArray) {
		 	if($indexArray['Key_name']==='PRIMARY') {
		 		$oIndexes->primaryKey = $indexArray['Column_name'];
		 	} else {
		 		if(isset($oIndexes->keys[$indexArray['Key_name']])) {
		 			$oIndexes->keys[$indexArray['Key_name']]->columns[] = $indexArray['Column_name'];
		 		} else {
			 		$x = new \stdClass();
			 		$x->name = isset($indexArray['Key_name']) ? $indexArray['Key_name'] : NULL;
			 		$x->collation = isset($indexArray['Collation']) ? $indexArray['Collation'] : NULL;
			 		$x->cardinality = isset($indexArray['Cardinality']) ? $indexArray['Cardinality'] : NUll;
			 		$x->type = isset($indexArray['Index_type']) ? $indexArray['Index_type'] : NULL;
			 		$x->comment = isset($indexArray['Index_Comment']) ? $indexArray['Index_Comment'] : '';
			 		$x->columns[] = isset($indexArray['Column_name']) ? $indexArray['Column_name'] : 'undefined';
			 		$oIndexes->keys[$indexArray['Key_name']]  = $x;
		 		}
		 	}
		 }
		return $oIndexes;
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