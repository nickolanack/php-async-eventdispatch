<?php
namespace asyncevent;

class ClosureHandler extends Handler
{
	


	protected $getListeners;
	protected $handler;
	protected $environment;

	public function __construct($config){

		if(is_object($config)){
			$config=get_object_vars($config);
		}

		if(!key_exists('getEventListeners', $config)){
			throw new \Exception('ClosureHandler requires getEventListeners parameter');
		}

		if(!key_exists('handleEvent', $config)){
			throw new \Exception('ClosureHandler requires handleEvent parameter');
		}

		$this->getListeners=$config['getEventListeners'];
		$this->handler=$config['handleEvent'];

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