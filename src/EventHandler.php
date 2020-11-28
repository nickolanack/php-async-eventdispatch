<?php
namespace asyncevent;

class EventHandler extends Handler
{
	


	protected $getListeners;
	protected $handler;
	protected $environment;

	public function __construct($config){

		if(is_object($config)){
			$config=get_object_vars($config);
		}

		

		if(!key_exists('handleEvent', $config)){
			throw new \Exception('EventHandler requires handleEvent parameter');
		}
		$this->handler=$config['handleEvent'];



		if(!key_exists('getEventListeners', $config)){

			/**
			 * generally when used as a dispatcher different handlers or listeners are 
			 * expected to be resolved by the getEventListeners arg. However, this is optional
			 * to support very simple schedule applications
			 *
			 * if no getEventListeners method is defined. a default listener ($this) is resolved 
			 * so that the handleEvent method recieves a single execution with a null $listener argument
			 */

			error_log('EventHandler expects getEventListeners parameter');
			//throw new \Exception('EventHandler requires getEventListeners parameter');
		}else{
			$this->getListeners=$config['getEventListeners'];
		}

		if(key_exists('setEnvironment', $config)){
			$this->environment=$config['setEnvironment'];
		}


	}
	public function setEnvironmentVariables($env){
		
		$environment=$this->environment;
		if($environment){
			echo 'Apply environment variables from cli: '.json_encode($env)."\n";
			$environment($env);
		}else{
			echo 'Implementor did not apply environment variables from cli: '.json_encode($env)."\n";
		}
	}
		
	

	public function getEventListeners($event){

		$getListeners=$this->getListeners;

		if(is_null($getListeners)){
			return [$this];
		}

		return $getListeners($event);

	}
	public function addEventListener($event, $function){
		throw new \Exception('Cannot add listeners. there is no storage');
	}


	public function handleEvent($listener, $event, $eventArgs){
		$handler=$this->handler;
		$handler($listener, $event, $eventArgs);
	}

}