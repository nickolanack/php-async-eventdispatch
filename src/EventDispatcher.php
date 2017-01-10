<?php
namespace asyncevent;

class EventDispatcher
{
	
	protected $emitter;
	protected $handler;

	public function __construct($config){

		if(is_object($config)){
			$config=get_object_vars($config);
		}

		if(!key_exists('eventEmitter', $config)){
			throw new \Exception('ClosureHandler requires eventEmitter parameter');
		}

		$this->emitter=$config['eventEmitter'];
		if(!($this->emitter instanceof EventEmitter)){
			throw new \Exception('ClosureHandler expects eventEmitter to implement EventEmitter');
		}

		if(key_exists('eventHandler', $config)){
			$this->handler=$config['eventHandler'];
			if(!($this->handler instanceof Handler)){
				throw new \Exception('ClosureHandler expects eventHandler to implement Handler');
			}
		}
		

	}


	public function emit($event, $data){

		$this->emitter->fireEvent($event, $data);


	}
	public function emitSync($event, $data){

		$this->emitter->fireEventSync($event, $data);

	}

	public function addAsyncListener($object, $event){}
	public function scheduleEvent($event, $data, $secondsFromNow){

	}

	public function shouldHandleEvent(){


		return $this->emitter->hasEvent();
	}

	public function handleEvent(Handler $handler=null){

		if($handler){
			$this->handler=$handler;
		}

		if(!$this->handler){
			throw new \Exception('EventDispatcher Requires a Handler object');
		}
		$this->handler->setEnvironmentVariables($this->emitter->getEnvironmentVariables());
		$this->handler->handleEvent($this->emitter->getEvent(), $this->emitter->getEventArgs());

			
	}  
}
