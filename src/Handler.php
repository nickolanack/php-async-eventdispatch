<?php
namespace asyncevent;
abstract class Handler{

	abstract public function getEventListeners($event);
	abstract public function addEventListener($object, $event);
	abstract public function setEnvironmentVariables($env);
	
	abstract protected function handle($listener, $object, $event);

	public function handleEvent($event, $eventArgs){

		foreach($this->getEventListeners($event) as $listener){


			$pid=-1;
			if(function_exists('pcntl_fork')){
				$pid = pcntl_fork();
			}
			
			if ($pid == -1) {
			     echo getmypid().' Unable to fork'."\n";
			    $this->handle($listener, $event, $eventArgs);

			} else if ($pid) {
			     // we are the parent
			     pcntl_wait($status); //Protect against Zombie children
			     echo getmypid().' Parent Finished'."\n";
			} else {
			   $this->handle($listener, $event, $eventArgs);

			}

		}

	}

}