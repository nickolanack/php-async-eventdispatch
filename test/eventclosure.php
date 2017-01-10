<?php

echo getmypid().' Event Runner Test'."\n";

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

	},
	'getEventListeners'=>function($event)use(&$dispatcher){

		return array(
			function($event, $eventArgs)use(&$dispatcher){
				echo getmypid().' Event listener (callback function) for event: '.$event.' '.json_encode($eventArgs)."\n";
				if($event=='testEvent'){
					echo getmypid().' Emit testEvent'."\n";
					$dispatcher->emit('testEvent', array(
						'hello'=>'world', 
					));
				}
			},
			function($event, $eventArgs)use(&$dispatcher){
				echo getmypid().' Event listener (callback function) for event: '.$event.' '.json_encode($eventArgs)."\n";
				if($event=='testEvent'){
					echo getmypid().' Emit testEvent'."\n";
					$dispatcher->emit('testEvent', array(
						'hello'=>'world', 
					));
				}
			}
		);

	},
	'handleEvent'=>function($listener, $event, $eventArgs){
		$listener($event, $eventArgs);
	}
));


$dispatcher=new asyncevent\EventDispatcher(array(
	'eventEmitter'=>$emitter, 
	'eventHandler'=>$handler
));



/*
 * 
 */
if($dispatcher->shouldHandleEvent()){

	echo getmypid().' Should Handle'."\n";
	$dispatcher->handleEvent();
}else{
	echo getmypid().' Emit testEvent'."\n";
	$dispatcher->emit('testEvent', array(
		'hello'=>'world',
	));
}
