<?php
require_once("../config.php"); // pointing to AppRoot



#require_once("../../../Api/api/api.php");
// would be in a real app:
// require_once($ENV->dirs->vendor."jeffstagedoor/Api/api/api.php");


require_once("../../../Api/api/Api.class.php");
$Api = new Jeff\Api\Api($ENV);