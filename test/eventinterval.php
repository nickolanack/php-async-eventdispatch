<?php

if (realpath($argv[0]) !== __FILE__) {
	exit();
}

require dirname(__DIR__) . '/vendor/autoload.php';

$dispatcher = new asyncevent\AsyncEventDispatcher(array(
	'command' => 'php ' . __FILE__,
	'getEnvironment' => function () {
		//get environment variables for passing to shell_exec on cli
		return array(
			'thetime' => time(),
		);
	},
	'log' => __DIR__ . '/.schedule.log',
	'handler' => asyncevent\FileScheduler::class,
	'schedule' => __DIR__ . '/schedules',
));

if ($dispatcher->shouldHandleEvent()) {

	//echo getmypid().' Should Handle'."\n";
	$dispatcher->handleEvent(
		array(
			'setEnvironment' => function ($env) {

			},
			'getEventListeners' => function ($event) use (&$dispatcher) {

				return array(
					function ($event, $eventArgs) use (&$dispatcher) {

						echo 'Event ' . date('H:i:s');

					},
				);

			},
			'handleEvent' => function ($listener, $event, $eventArgs) {
				$listener($event, $eventArgs);
			},
		)
	);
	return;
}

echo getmypid() . ' Schedule Runner Test' . "\n";

for ($i = 0; $i < 600; $i++) {
	$dispatcher->scheduleInterval('testEvent', array(
		'hello' => 'world',
	), 5);
	usleep(50000);
}
$dispatcher->clearInterval('testEvent');

for ($i = 0; $i < 60; $i++) {
	$dispatcher->scheduleInterval('testEvent', array(
		'hello' => 'world',
	), 10);
	usleep(500000);
}

$dispatcher->clearAllIntervals();
