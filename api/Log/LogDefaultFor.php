<?php
/**
 * Log Default For Object.
 * 
 * To be instatiated in LogConfig.php of consuming app in static function values(): 
 * 
 * ```
 * 	public static function values() {
 * 		$values = new \stdClass();
 *      // ... setLogDefaultMeta
 *		$values->posts = new \stdClass();
 *		$values->posts->for = new LogDefaultFor(
 *				NULL, Constants::USER_ADMIN, 		//A
 *				Array("post","id"), Constants::WORKGROUPS_ADMIN,	// B = workgroup
 *				NULL, NULL,		// C = production
 *				NULL, NULL 		// D = audition
 *			);
 *      return $values;
 *  }
 * ```
 */
Class LogDefaultFor {
	public $A;
	public $ARights;
	public $B;
	public $BRights;
	public $C;
	public $CRights;
	public $D;
	public $DRights;

	function __construct($A, $ARights, $B, $BRights, $C, $CRights, $D, $DRights) {
		$this->A 		= $A;
		$this->ARights 	= $ARights;
		$this->B 		= $B;	
		$this->BRights 	= $BRights;
		$this->C 		= $C;
		$this->CRights 	= $CRights;
		$this->D 		= $D;
		$this->DRights 	= $DRights;
	}
}