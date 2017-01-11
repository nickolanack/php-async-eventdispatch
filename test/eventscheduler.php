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
	'log'=>function($message)use(&$dispatcher){

		file_put_contents(__DIR__.'/.schedule.log', str_pad('', $dispatcher->getDepth()*4).getmypid().' '.date('Y-m-d H:i:s') . ' ' . $message . "\n", FILE_APPEND);

	}
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


$dispatcher->schedule('testEvent', array(
	'hello'=>'world',
), 50);

