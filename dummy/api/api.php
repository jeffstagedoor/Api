<?php
require_once('../../api/Environment.php');
require_once("../config.php"); // pointing to AppRoot

// Constants are only used in consuming app, so this is optional
require_once("../Constants.php"); // pointing to AppRoot

use Jeff\Api\Api;
use Jeff\Api\Environment;

require_once(Environment::$dirs->api."Api.php");
$Api = Api::getInstance();
$Api->start();