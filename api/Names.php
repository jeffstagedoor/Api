<?php
/**
*	Class Names
*	
*	@author Jeff Frohner
*	@copyright Copyright (c) 2015
*	@license   private
*	@version   1.0.0
*
**/

namespace Jeff\Api;

Class Names {

	private static $prename = array("von"=>1, "van"=>1, "del"=>1, "dal"=>1, "dallo"=>1, "dello"=>1, "de"=>1, "di"=>1,"der"=>1, "du"=>1, "lo"=>1, "los"=>1, "il"=>1, "la"=>1);

	/**
	* versucht einen FullName in VorName, MiddleName, Prefix, Nachname zu splitten
	* @return [array of strings] (vname, mname, prenname, nname)
	**/
	static function Arrange($y) {
		$x=explode(" ",$y);
		switch (count($x)) {
			case 1:
				return false;
				break;
			case 2:
				# dann geh ich mal von vorname nachname aus
				$namearr = array($x[0],"","",$x[1]);
				return $namearr;
				break;
			case 3:
				# herausfinden ob die Mitte was von "van, del , di, ..." hat
				# wenn nicht, ist es Mittelname
				if(isset(self::$prename[$x[1]])) { #mitte ist ein "von"
					$namearr = array($x[0],"",$x[1],$x[2]);
					#$recheck=1;
				} else {
					$namearr = array($x[0],$x[1],"",$x[2]);
				}
				return $namearr;
				break;
			case 4:
				# herausfinden ob die Mitte 1 oder 2 was von "van, del , di, ..." hat
				if(isset(self::$prename[$x[1]])) { #mitte ist ein "von"
					if(isset(self::$prename[$x[2]])) { #das dannach auch
						$namearr = array($x[0],"",$x[1]." ".$x[2],$x[3]);					
					} else {	# das kann eigentlich nicht sein. das wäre etwas wie "Klaus van Michael Mustermann"
						$namearr = array($x[0],$x[1],$x[2],$x[3]);
						return false;
						break;
					}
				} else if (isset(self::$prename[$x[2]])){
					$namearr = array($x[0],$x[1],$x[2],$x[3]);
				} else {
					$namearr = array($x[0],$x[1]." ".$x[2],"",$x[3]);			
				}
				return $namearr;
				break;
		}
	}
}