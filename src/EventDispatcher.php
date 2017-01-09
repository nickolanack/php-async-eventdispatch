<?php
namespace asyncevent;

class EventDispatcher
{
	
	protected $emitter;
	protected $listeners;

	public function __construct(EventEmitter $emitter, Listeners $listeners){

		$this->emitter=$emitter;
		$this->listeners=$listeners;

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

	public function handleEvent(){

		$this->listeners->handleEvent($this->emitter->getEvent(), $this->emitter->getEventArgs());

			
	}  
}
