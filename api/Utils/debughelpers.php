<?php
/**
* DEBUG HELPERS (global)
*
*/



/**
* echoes a debug info
* Usage:
*
* ```
* debug('test', __FILE__, __LINE__ , get_class());
* ```
*
* @param string $string The actual value to print
* @param string $file current file name (including path)
* @param string $line line number of current script
* @param string $class current className
*/
function debug($string, $file='', $line='', $class='') {
	echo $file ? basename($file).": " : '';
	echo $class ? $class.": " : '';
	echo $line ? $line.": " : '';
	echo $string."\n";
}


/**
* alias for debug, BUT also stops execution with `exit;`
*
* Usage:
*
* ```
* halt('test', __FILE__, __LINE__ , get_class());
* ```
*
* @param string $string The actual value to print
* @param string $file current file name (including path)
* @param string $line line number of current script
* @param string $class current className
*/

function halt($string, $file='', $line='', $class='') {
	debug($string, $file, $line, $class);
	exit;
}