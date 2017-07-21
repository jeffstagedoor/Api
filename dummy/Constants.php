<?php

namespace Jeff;

abstract class Constants {
	
	// RIGHTS - MANAGEMENT
	const USER_DISABLED = 0;
	const USER_USER = 1;
	const USER_ADMIN = 2;
	const USER_SUPERADMIN = 3;

	const WORKGROUPS_CONTESTENT = 0;
	const WORKGROUPS_MEMBER = 1;
	const WORKGROUPS_ADMIN = 2;

	const PRODUCTIONS_ARTIST = 1;
	const PRODUCTIONS_HODT = 3;
	const PRODUCTIONS_HODC = 4;
	const PRODUCTIONS_SM = 5;
	const PRODUCTIONS_CM = 7;
	const PRODUCTIONS_ADMIN = 9;

	const AUDITIONS_ADMIN = 9;

	// END RIGHTS MANAGEMENT
}


// Class Rights extends SplEnum {
// 	Account = new stdClass();
// 	Account->DISABLED = 0;
// 	Account->USER = 1;
// 	Account->ADMIN = 2;
// 	Account->SUPERADMIN = 3;
// 	Workgroup = new stdClass();
// 	Workgroup->CONTESTENT = 0;
// 	Workgroup->MEMBER = 1;
// 	Workgroup->ADMIN = 2;
// 	Production = new stdClass();
// 	Production->ARTIST = 1;
// 	Production->HOD_TECH = 3;
// 	Production->HOD_CREATIVE = 4;
// 	Production->STAGEMANAGEMENT = 5;
// 	Production->COMPANYMANAGEMENT = 7;
// 	Production->ADMIN = 9;
// }


?>