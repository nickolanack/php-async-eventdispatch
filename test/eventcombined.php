<?php

require dirname(__DIR__) . '/vendor/autoload.php';

$dispatcher = new asyncevent\AsyncEventDispatcher(array(
	'log' => __DIR__ . '/.schedule.log',
	'handler' => asyncevent\FileScheduler::class,
	'schedule' => __DIR__ . '/schedules',
));

if ($dispatcher->shouldHandleEvent(
	function ($listener, $event, $eventArgs) {
		
		echo 'Event ' . date('H:i:s') . json_encode($eventArgs);
	
	})) {

	return;
}

echo getmypid() . ' Schedule Runner Test' . "\n";

$dispatcher->scheduleInterval('testInterval1', array(
	'interval' => 1,
), 5);

$dispatcher->scheduleInterval('testInterval2', array(
	'interval' => 2,
), 15);

$dispatcher->scheduleInterval('testInterval3', array(
	'interval' => 3,
), 25);

for ($i = 0; $i < 300; $i++) {
	$dispatcher->schedule('testEvent', array(
		'hello' => 'world - ' . $i,
	), rand(10, 100));
	usleep(50000);
}

for ($i = 0; $i < 500; $i++) {
	$dispatcher->throttle('testEvent', array(
		'hello' => 'world - ' . $i,
	), array('interval' => 5), rand(0, 20));

	usleep(50000);
}

sleep(30);

$dispatcher->clearAllIntervals();
