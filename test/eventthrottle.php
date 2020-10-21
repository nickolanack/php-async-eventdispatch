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

	//echo getmypid().' Should Handle'."\n";
	$dispatcher->handleEvent(
		array(
			'setEnvironment' => function($env){
				
			},
			'getEventListeners'=>function($event)use(&$dispatcher){

				return array(
					function($event, $eventArgs)use(&$dispatcher){

						echo 'Event handler message: '.date('H:i:s');
					
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

echo getmypid().' Schedule Throttle Test'."\n";
echo getmypid().' Event name: testEvent'."\n";


for($i=0;$i<100;$i++){
	$dispatcher->throttle('testEvent', array(
		'hello'=>'world',
	), array(), rand(10, 100));
}

