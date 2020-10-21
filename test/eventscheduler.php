<?php


require dirname(__DIR__).'/vendor/autoload.php';



$dispatcher=new asyncevent\AsyncEventDispatcher(array(
	'command'=>'php '.__FILE__, 
	'getEnvironment'=>function(){
		//get environment variables for passing to shell_exec on cli
		return array(
			'thetime'=>time()
		);
	},
	'log'=>dirname(__DIR__).'/.schedule.log',
	'handler'=>asyncevent\FileScheduler::class,
	'schedule'=>__DIR__
));

if($dispatcher->shouldHandleEvent()){

	echo getmypid().' Should Handle'."\n";
	$dispatcher->handleEvent(
		array(
			'setEnvironment' => function($env){
				
			},
			'getEventListeners'=>function($event)use(&$dispatcher){

				return array(
					function($event, $eventArgs)use(&$dispatcher){

						echo 'Event '.date('H:i:s');
					
					}
				);

			},
			'handleEvent'=>function($listener, $event, $eventArgs){
				$listener($event, $eventArgs);
			}
		)
	);
	return; 
}

echo getmypid().' Schedule Runner Test'."\n";
echo getmypid().' Schedule testEvent'."\n";

echo 'Expected Event: '.date('H:i:s', time()+50);

for($i=0;$i<100;$i++){
	$dispatcher->schedule('testEvent', array(
		'hello'=>'world',
	), rand(100, 150));
}

