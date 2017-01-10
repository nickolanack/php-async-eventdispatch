<?php


require dirname(__DIR__).'/vendor/autoload.php';



/**
 * Initialize an emitter. an emitter is responsible for firing events, in this case calling 
 * shell_exec, on this file (__FILE__) with event arguments (detected below)
 *
 * The environment variables try to simulate http environment variables but this is actually going to execute on cli
 */



$emitter=new asyncevent\ShellEventEmitter(array(
	'command'=>'php '.__FILE__, 
	'getEnvironment'=>function(){
		//get environment variables for passing to shell_exec on cli
		return array(
			'session'=>'testsession',
			'domain'=>'www.example.com',
			'scriptpath'=>basename(__FILE__),
			'ip'=>'0.0.0.0'
		);
	}
	
));

/**
 * Initialize a handler. A handler is responsible applying environment variable, resolving event listener objects, 
 * and for calling event handler methods on event listener objects. ClosureHandler allows simple user defined 
 */


$handler=new asyncevent\ClosureHandler(array(
	'setEnvironment' => function($env){
		//This will be called each time a new process is created 
		//$env is an array of key value pairs passed to thhe
		echo 'Set Env '.json_encode($env)."\n";

	},
	'getEventListeners'=>function($event)use(&$dispatcher){

		return array(
			function($event, $eventArgs)use(&$dispatcher){

				sleep(2);

				echo getmypid().' Event listener 1 (callback function) for event (and emits recursively): '.$event.' '.json_encode($eventArgs)."\n";
				if($event=='testEvent'){
					echo getmypid().' Emit recursive testEvent at depth: '.$dispatcher->getDepth()."\n";
					$dispatcher->emit('testEvent', array(
						'hello'=>'world', 
					));
				}
				
			},
			function($event, $eventArgs)use(&$dispatcher){

				sleep(2);

				echo getmypid().' Event listener 2 (callback function)'."\n";
				
			}
			//, ... more event listeners for this event.
		);

	},
	'handleEvent'=>function($listener, $event, $eventArgs){
		$listener($event, $eventArgs);
	}
));


$dispatcher=new asyncevent\EventDispatcher(array(
	'eventEmitter'=>$emitter, 
	'eventHandler'=>$handler,
	'log'=>function($message)use(&$dispatcher){
		file_put_contents(__DIR__.'/.closure.log', str_pad('', $dispatcher->getDepth()).getmypid().' '.date_format(date_create(), 'Y-m-d H:i:s') . ' ' . $message . "\n", FILE_APPEND);
	}
));



/*
 * 
 */
if($dispatcher->shouldHandleEvent()){

	echo getmypid().' Should Handle'."\n";
	$dispatcher->handleEvent();
	return; 
}


echo getmypid().' Event Runner Test'."\n";
echo getmypid().' Emit testEvent'."\n";
$dispatcher->emit('testEvent', array(
	'hello'=>'world',
));

