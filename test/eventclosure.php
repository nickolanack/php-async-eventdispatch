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
			'session' => 'testsession',
			'domain' => 'www.example.com',
			'scriptpath' => basename(__FILE__),
			'ip' => '0.0.0.0',
		);
	},
	'log' => __DIR__ . '/.closure.log',
	'schedule' => 'localhost:11211/awesome',
	'handler' => \asyncevent\schedulers\MemcachedScheduler::class,
));

/*
 *
 */
if ($dispatcher->shouldHandleEvent()) {

	// echo getmypid() . ' Should Handle' . "\n";
	$dispatcher->handleEvent(
		array(
			'setEnvironment' => function ($env) {
				//This will be called each time a new process is created
				//$env is an array of key value pairs passed to thhe
				echo 'Set Env ' . json_encode($env) . "\n";

			},
			'getEventListeners' => function ($event) use (&$dispatcher) {

				return array(
					function ($event, $eventArgs) use (&$dispatcher) {

						usleep(100);

						echo getmypid() . ' Event listener 1 (callback function) for event (and emits recursively): ' . $event . ' ' . json_encode($eventArgs) . "\n";
						if ($event == 'testEvent') {
							echo getmypid() . ' Emit recursive testEvent at depth: ' . $dispatcher->getDepth() . "\n";
							$dispatcher->schedule('testEvent', array(
								'hello' => 'world',
							), 0);
						}

					},
					function ($event, $eventArgs) use (&$dispatcher) {

						usleep(10);

						echo getmypid() . ' Event listener 2 (callback function)' . "\n";

					},
					//, ... more event listeners for this event.
				);

			},
			'handleEvent' => function ($listener, $event, $eventArgs) {
				$listener($event, $eventArgs);
			},
		)
	);
	return;
}

echo getmypid() . ' Event Runner Test' . "\n";
echo getmypid() . ' Emit testEvent' . "\n";
for ($i = 0; $i < 100; $i++) {
	$dispatcher->emit('testEvent', array(
		'hello' => 'world',
	));
	usleep(10);
}
