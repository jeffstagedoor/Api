<?php
/**
 * Log Default Meta Object.
 * 
 * To be instatiated in LogConfig.php of consuming app in static function values(): 
 * 
 * ```
 * 	public static function values() {
 * 		$values = new \stdClass();
 *      // ... setLogDefaultFor
 *		$values->posts = new \stdClass();
 * 		$values->posts->meta = new LogDefaultMeta(
 *				Array("posts", "id"),	// int
 *				null, // int
 *				null, // int
 *				Array("posts", "N_TITEL"), // string 80
 *				Array("posts", "N_TXT")	// string 255
 *			);
 *      return $values;
 *  }
 * ```
 */
Class LogDefaultMeta {
	public $Meta1;
	public $Meta2;
	public $Meta3;
	public $Meta4;
	public $Meta5;

	function __construct($A=null, $B=null, $C=null, $D=null, $E=null) {
		$this->Meta1 = $A;
		$this->Meta2 = $B;
		$this->Meta3 = $C;
		$this->Meta4 = $D;
		$this->Meta5 = $E;
	}
}