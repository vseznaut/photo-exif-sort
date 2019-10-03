<?php

set_time_limit(0);
require("classes/class.db.php");
require("classes/class.files.php");
require("classes/class.geo.php");
$config = require('config/config.php');

$files 	= new Files();
$db 		= new Db();
$geo 		= new Geo();

$db->connect();
$file_list = $files->getDirContents($config['photos.unsorted']);


foreach ($file_list as $key => $value) {
	$files->moveImageFile($value, $db, $geo, $config);
}

exit;
