<?php


include_once __DIR__ . '/../vendor/autoload.php';

$longOpts = array(
	'schedule:',
	'handler:'
);
$args = getopt('', $longOpts);

$schedule = $args['schedule'];


echo ($schedule)."\n";

if(array_key_exists('handler', $args)){



	$class=$args['handler'];



	error_log($class);

	(new $class($schedule))
	//->run($file);
	->queue($schedule)
	->checkProcesses();

	return;
}

error_log( "Using default: ".json_encode($args));


(new \asyncevent\FileScheduler($schedule))
	//->run($file);
	->queue($schedule)
	->checkProcesses();