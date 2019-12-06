<?php

if (PHP_MAJOR_VERSION < 7){
	die("PHP QRCode only works for PHP 7+");
}

if (php_sapi_name() != "cli") {
	chdir("../");
}
	
spl_autoload_register(function ($class_name) {
	$filename = str_replace('\\', DIRECTORY_SEPARATOR, $class_name) . '.php';
	include $filename;
});
