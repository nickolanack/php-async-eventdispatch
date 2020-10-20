<?php

include_once __DIR__ . '/../vendor/autoload.php';

$longOpts = array(
	'dir:',
);
$args = getopt('', $longOpts);

$dir = $args['dir'];


(new \asyncevent\FileScheduler($dir))
	->checkProcesses($dir);