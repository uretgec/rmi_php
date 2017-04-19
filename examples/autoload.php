<?php
spl_autoload_register(function ($className) {
	$baseClass = explode('Rmi\\',$className);
	$filename = __DIR__."/.." . implode('/',$baseClass) . ".php";
//	var_dump($filename);
	if (file_exists($filename)) {
		include($filename);
		if (class_exists($className))
			return true;
	}
	return false;
});