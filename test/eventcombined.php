<?php

/**
 * WARNING this test schedules >30000 events within a few seconds
 */

require dirname(__DIR__) . '/vendor/autoload.php';

$dispatcher = new asyncevent\AsyncEventDispatcher(array(
	'log' => __DIR__ . '/.schedule.log',
	'handler' => asyncevent\FileScheduler::class,
	'schedule' => __DIR__ . '/schedules',
));

if ($dispatcher->shouldHandleEvent(
	function ($listener, $event, $eventArgs) use($dispatcher){
		
		echo 'Event ' . date('H:i:s') . json_encode($eventArgs);
	
		if($event=='testEvent1'){
			for ($i = 0; $i < 10; $i++) {
				$dispatcher->schedule('testEvent3', array(
					'hello' => 'world - ' . $i,
				), rand(5, 20));
				usleep(5000);
			}
		}



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

for ($i = 0; $i < 3000; $i++) {
	$dispatcher->schedule('testEvent1', array(
		'hello' => 'world - ' . $i,
	), rand(10, 100));
	usleep(5000);
}

for ($i = 0; $i < 1000; $i++) {
	$dispatcher->throttle('testEvent2', array(
		'hello' => 'world - ' . $i,
	), array('interval' => 5), rand(0, 20));

	usleep(5000);
}

sleep(30);

$dispatcher->clearAllIntervals();
