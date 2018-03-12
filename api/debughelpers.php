<?php
/*
* DEBUG HELPERS
*/

function debug($string, $file='', $line='', $class='') {
	echo $file ? basename($file).": " : '';
	echo $class ? $class.": " : '';
	echo $line ? $line.": " : '';
	echo $string."\n";
}

function halt($string, $file='', $line='', $class='') {
	debug($string, $file, $line, $class);
	exit;
}