<?php
namespace asyncevent;

class ExternalListeners extends Listeners
{
	


	protected $getListeners;
	protected $handler;

	public function __construct($getListeners, $handler){

		$this->getListeners=$getListeners;
		$this->handler=$handler;

	}

	public function getEventListeners($event){

		$getListeners=$this->getListeners;
		return $getListeners($event);

	}
	public function addEventListener($event, $function){
		throw new Exception('Cannot add listeners. there is no storage');
	}


	protected function handle($listener, $event, $eventArgs){
		$handler=$this->handler;
		$handler($listener, $event, $eventArgs);
	}

}