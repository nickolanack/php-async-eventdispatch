<?php
namespace asyncevent;

class AsyncEventDispatcher extends EventDispatcher
{

	public function __construct($config){

		parent::__construct(array_merge($config, array('eventEmitter'=>new AsyncEventEmitter($config))));

	}

	public function handleEvent($config){

		if($config instanceof Handler){
			parent::handleEvent($config);
		}else{
			parent::handleEvent(new EventHandler($config));
		}
	}
}