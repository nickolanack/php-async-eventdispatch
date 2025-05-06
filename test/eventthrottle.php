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
	'schedule' => 'localhost:11211/awesome',
	'handler' => \asyncevent\schedulers\MemcachedScheduler::class,
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

						echo 'Event handler message: ' . date('H:i:s');

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

echo getmypid() . ' Schedule Throttle Test' . "\n";
echo getmypid() . ' Event name: testEvent' . "\n";

for ($i = 0; $i < 500; $i++) {
	$dispatcher->throttle('testEvent', array(
		'hello' => 'world',
	), array('interval' => 5), rand(0, 20));
}
