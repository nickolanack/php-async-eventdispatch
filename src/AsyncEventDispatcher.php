<?php
namespace asyncevent;

class AsyncEventDispatcher extends EventDispatcher
{

	public function __construct($config){

		parent::__construct(array_merge($config, array('emitter'=>new AsyncEventEmitter($config));

	}

	public function handleEvent($config){

		if($config instanceof Handler){
			self::parent($config);
		}else{
			self::parent(new EventHandler($config));
		}
	}
}