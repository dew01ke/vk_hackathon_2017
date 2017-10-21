<?php

error_reporting(1);

require_once("lt_entities.php");
require_once("morphy/common.php");

$morphyConfig = [
	"dir" => "./var",
	"lang" => "ru_RU"
];

$morphy = new phpMorphy($morphyConfig['dir'], $morphyConfig['lang'], []);
$res = $morphy->lemmatize('ВЕЛОСИПЕДАМИ', phpMorphy::NORMAL);
print_r($res);


?>