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