<?php
namespace asyncevent;
abstract class Handler{

	abstract public function getEventListeners($event);
	abstract public function addEventListener($object, $event);
	abstract public function setEnvironmentVariables($env);
	
	abstract public function handleEvent($listener, $object, $event);

	

}