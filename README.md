# php-async-eventdispatch #

I want my web apps to be really fast, and not have the overhead of system events, but unfortunately I can't fork processes under apache, so this implementation, executes using shell_exec/system to start a new process (environment variables are passed to process) and listener methods are executed in the new process (fork is used in the new process which should become available now that it is outside of apache) to avoid problems with multiple listeners, ie: if the first listener sleeps for some time then other listeners might have to wait

Usage
```php

require __DIR__.'/vendor/autoload.php';

$emitter=new asyncevent\ShellEventEmitter(array(
	'command'=>'php '.__FILE__, 
	'getEnvironment'=>function(){
		//get some environment variables to pass to shell_exec on cli
		//put whatever you need here this is just an example
		return array(
			'session'=>'0000000000',
			'access_token'=>'0000000000'
			//things like domain name, ip address, web browser might be useful
		);
	}
	
));

$dispatcher=new asyncevent\EventDispatcher(array(
	'eventEmitter'=>$emitter
));

if($dispatcher->shouldHandleEvent()){

	$handler=new asyncevent\ClosureHandler(array(
		'setEnvironment' => function($env){
		
			// these are the variables you passed to the emitter, they came back from the command line
			$system->setSession($env['session']);
			$system->setUserFromAccessToken($env['access_token'])
			
		},
		'getEventListeners'=>function($event)use(&$dispatcher){
		
			// resolve event listeners. 
			// whatever you return here will just become available to you 
			// in 'handleEvent' below, so it could be objects or strings, ids...
			return array(
				instantiateSomeClass(...)
			);

		},
		'handleEvent'=>function($listener, $event, $eventArgs){
			
			$listener->someEventMethod($event, $eventArgs);
			
		}
	));

	$dispatcher->handleEvent($handler);
	return;
}


```
