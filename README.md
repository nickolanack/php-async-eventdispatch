# php-async-eventdispatch #

I want my web apps to be really fast, and not have the overhead of system events, but unfortunately I can't fork processes under apache.

This implementation, handles events in a new process by using shell_exec/system commands (environment variables are passed to process from cli). All listener methods are executed in the new process and can emit thier own events (up to max recursive depth) and othewise take as long as they need.  

~~pcntl_fork is used/attempted in the handler process (which should have become available now that it is outside of apache) to avoid problems with multiple listeners, ie: if the first listener sleeps for some time then other listeners might have to wait~~
unless you explicitly enable forking: 'fork' => true, when instantiating the dispatcher, forking will not be used. 


Usage - Configuration;
```php

require __DIR__.'/vendor/autoload.php';

$dispatcher=new asyncevent\AsyncEventDispatcher(array(
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
	//, 'log'=>function($message){} or String path
));

if($dispatcher->shouldHandleEvent()){


	$dispatcher->handleEvent(
		array(
			//'fork' => true,
			'setEnvironment' => function($env){
		
				// these are the variables you passed to the emitter, they came back from the command line
				$system->setSession($env->session);
				$system->setUserFromAccessToken($env->access_token)
				
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
		)
	);
	return; //this process should be finished after executing events.
}


// render web app ...  

// $dispatcher->emit($event, $eventArgs);

// schedule an event to be executed later
// $dispatcher->schedule($event, $eventArgs, $seconds);

```

### Memcached Quick Start

memcached up and running quickly in a docker container

```

docker run -d --name memcached -p 11211:11211 memcached

```