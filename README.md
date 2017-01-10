# README #


Usage
```php




require __DIR__.'/vendor/autoload.php';



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
		
		//for example
		return array(
			'session'=>'0000000000',
			'access_token'=>'0000000000'
			//things like domain name, ip address, web browser might be useful
		);
	}
	
));

/**
 * Initialize a handler. A handler is responsible applying environment variable, resolving event listener objects, 
 * and for calling event handler methods on event listener objects. ClosureHandler allows simple user defined 
 */



$dispatcher=new asyncevent\EventDispatcher(array(
	'eventEmitter'=>$emitter
));



/*
 * 
 */
if($dispatcher->shouldHandleEvent()){

	$handler=new asyncevent\ClosureHandler(array(
		'setEnvironment' => function($env){
			//This will be called each time a new process is created 
			//$env is an array of key value pairs passed from cli (your variables above)

			//for example
			$system->setSession($env['session']);
			$system->setUserFromAccessToken($env['access_token'])

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

	$dispatcher->handleEvent($handler);
	return;
}


```