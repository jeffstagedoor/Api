<?php
/**
 * This file contains class Names
 */

namespace Jeff\Api\Utils;

/**
 *	A Helper Class that generates fullNames (method 'createFullname()') out of parts and/or splits a fullName into it's parts (method 'Arrange()')
 *	
 *	@author Jeff Frohner
 *	@copyright Copyright (c) 2015
 *	@license   private
 *	@version   1.0.0
 *
 */
Class Names {

	/** @var array $prename A set of usual prefixNames, such as 'van', 'von', 'dal', 'di', 'los',.. */
	private static $prename = array("von"=>1, "van"=>1, "del"=>1, "dal"=>1, "dallo"=>1, "dello"=>1, "de"=>1, "di"=>1,"der"=>1, "du"=>1, "lo"=>1, "los"=>1, "il"=>1, "la"=>1);

	/**
	* versucht einen FullName in VorName, MiddleName, Prefix, Nachname zu splitten
	* @param string $fullName
	* @param string $returnType what type shall be returned. Default is 'array', anything else will result in an object to be returned
	* @return string[]|object (firstName, middleName, prefixName, lastName)
	**/
	public static function Arrange($fullName, $returnType='array') {
		$x=explode(" ",$fullName);
		switch (count($x)) {
			case 1:
				return false;
				break;
			case 2:
				// expexting firstName and lastName
				$namearr = array($x[0],"","",$x[1]);
				break;
			case 3:
				// herausfinden ob die Mitte was von "van, del , di, ..." hat
				// wenn nicht, ist es Mittelname
				if(isset(self::$prename[$x[1]])) { #mitte ist ein "von"
					$namearr = array($x[0],"",$x[1],$x[2]);
				} else {
					$namearr = array($x[0],$x[1],"",$x[2]);
				}
				break;
			case 4:
				// herausfinden ob die Mitte 1 oder 2 was von "van, del , di, ..." hat
				if(isset(self::$prename[$x[1]])) { // mitte ist ein "von"
					if(isset(self::$prename[$x[2]])) {  // das dannach auch
						$namearr = array($x[0],"",$x[1]." ".$x[2],$x[3]);					
					} else {	// das kann eigentlich nicht sein. das wäre etwas wie "Klaus van Michael Mustermann"
						$namearr = array($x[0],$x[1],$x[2],$x[3]);
						return false;
						break;
					}
				} else if (isset(self::$prename[$x[2]])){
					$namearr = array($x[0],$x[1],$x[2],$x[3]);
				} else {
					$namearr = array($x[0],$x[1]." ".$x[2],"",$x[3]);			
				}
				break;
		}
		if($returnType!='array') {
			$namearr = self::_createObjectFromArray($namearr);
		}
		return $namearr;
	}


	/**
	*	baut aus einem übergebenen Datensatz nach bester Möglichkeit ein vollständiges Name-Set zusammen
	*
	*	Fehlt einer der Namens-Teile (also zB es gibt fullName, aber nicht die Aufsplittung), so wird versucht
	*	die fehlenden zu erzeugen
	*	
	*	@param mixed $data 	ein Object oder Array, welches zum Teil folgende Keys hat:
	*						fullName, firstName, middleName, prefixName, lastName
	*	@return object 		gibt ein Object zurück mit den parametern
	*						fullName, firstName, middleName, prefixName, lastName
	*/
	public static function createNameSet($data) {
		if(!isset($data->fullName)) {
			// fullName zusammenbauen, falls alle anderen vorhanden sind
			if(isset($data->firstName) && isset($data->lastName)) { // Minimal-Anforderung
				$data->fullName = self::createFullName($data);
			} else {
				return null;
			}
		} else if (!isset($data->firstName) || !isset($data->middleName) || !isset($data->prefixName) || !isset($data->lastName)) {
			$nameParts = self::Arrange($data->fullName, 'Object');
			$data = (object) array_merge((array) $data, (array) $nameParts);
		}
		return $data;
	}

	/**
	* creates a fullName out of the split parts
	*	
	* @param object $data ein Object welches folgende Keys hat:
	*			 		  fullName, firstName, middleName, prefixName, lastName
	*  ```
	*  {
	*    firstName: 'Jost',
	*    middleName: 'Claudius',
	*    prefixName: 'van',
	*    lastName: 'Anderen'
	*  }
	*  ```
	*	
	* @return string gibt den generierten fullName zurück: 'Jost Claudius van Anderen'
	*/
	public static function createFullName($data) {
		$fullName = '';
		$fullName = (isset($data->firstName) && $data->firstName>'') ? ($data->firstName) : $fullName;
		$fullName = (isset($data->middleName) && $data->middleName>'') ? ($fullName.' '.$data->middleName) : $fullName;
		$fullName = (isset($data->prefixName) && $data->prefixName>'') ? ($fullName.' '.$data->prefixName) : $fullName;
		$fullName = (isset($data->lastName) && $data->lastName>'') ? ($fullName.' '.$data->lastName) : $fullName;
		return $fullName;
	}

	/**
	* creates an object of the input array:
	* @param array $array an array with the name parts
	* 
	* ```
	* [firstName, middleName, prefixName, lastName]
	* ```
	*
	* @return object
	* 
	* ```
	* {
	*    firstName: 'Jost',
	*    middleName: 'Claudius',
	*    prefixName: 'van',
	*    lastName: 'Anderen'
	* }
	* ```
	*/
	private static function _createObjectFromArray($array) {
		$o = new \stdClass();
		$o->firstName = $array[0];
		$o->middleName = $array[1];
		$o->prefixName = $array[2];
		$o->lastName = $array[3];
		return $o;
	}
}