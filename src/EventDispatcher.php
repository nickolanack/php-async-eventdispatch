<?php
namespace asyncevent;

class EventDispatcher
{
	
	protected $emitter;
	protected $handler;
	protected $logger;

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

		if(key_exists('log', $config)){
			$this->logger=$config['log'];	
		}
		if(!$this->logger){
			$this->logger=function($message){
				echo getmypid().' '.date_format(date_create(), 'Y-m-d H:i:s') . ' ' . $message . "\n";
			};
		}
		

	}


	public function emit($event, $eventArgs){

		$this->_log('Begin emitting: '.$event.'('.json_encode($eventArgs).')');
		$this->emitter->fireEvent($event, $eventArgs);
		$this->_log('Done emitting: '.$event);

	}
	public function emitSync($event, $eventArgs){

		$this->emitter->fireEventSync($event, $eventArgs);

	}

	public function scheduleEvent($event, $eventArgs, $secondsFromNow){

	}

	public function shouldHandleEvent(){
		return $this->emitter->hasEvent();
	}

	public function getTrace(){
		return $this->emitter->getTrace();
	}
	public function getDepth(){
		return $this->emitter->getDepth();
	}

	public function handleEvent(Handler $handler=null){

		if($handler){
			$this->handler=$handler;
		}

		if(!$this->handler){
			throw new \Exception('EventDispatcher Requires a Handler object');
		}


		ob_start();
		$event=$this->emitter->getEvent();
		$content=trim(ob_get_contents());
		if(!empty($content)){
			$this->_log($content);
		}
		ob_end_clean();



		ob_start();
		$eventArgs=$this->emitter->getEventArgs();
		$content=trim(ob_get_contents());
		if(!empty($content)){
			$this->_log($content);
		}
		ob_end_clean();

		$this->_log('Handling Event: '.$event);


		ob_start();
		$enviromentVars=$this->emitter->getEnvironmentVariables();
		$content=trim(ob_get_contents());
		if(!empty($content)){
			$this->_log($content);
		}
		ob_end_clean();


		ob_start();
		$this->handler->setEnvironmentVariables($enviromentVars);
		$content=trim(ob_get_contents());
		if(!empty($content)){
			$this->_log($content);
		}
		ob_end_clean();


		ob_start();
		$listeners=$this->handler->getEventListeners($event);
		$content=trim(ob_get_contents());
		if(!empty($content)){
			$this->_log($content);
		}
		ob_end_clean();

		





		$this->_handleEvent($listeners, $event, $eventArgs);

		$this->_log('Finished handling event: '.$event);

	}  


	protected function _handleEvent($listeners, $event, $eventArgs){

		$i=1;
		$children=array();
		foreach($listeners as $listener){


			$pid=-1;
			if(function_exists('pcntl_fork')){
				$pid = pcntl_fork();
			}
			
			if ($pid == -1) {
			    $this->_log('Unable to fork');
			    $this->_executeHandler($listener, $event, $eventArgs);

			} else if ($pid) {
				$children[]=$pid;
			     $this->_log('Forked parent->'.$pid);
			     
			    


			    
			} else {

				$this->_log('Child: #'.$i);
			    $this->_executeHandler($listener, $event, $eventArgs);
			    $this->_log('Child #'.$i.' finished');

			    //need to return here or the child will also fork and execute on the next loop
			    exit(0);

			}

			$i++;

		}
		if(!empty($children)){
			foreach($children as $index=>$child){
				//only the parent should get here becuase $children would be empty otherwise
				$this->_log('Wait for child #'.($index+1));
				pcntl_waitpid($child);
				$this->_log('Signaled exit from #'.($index+1));
			}
			$this->_log('Fork parent finished: '.getmypid().' -> '.json_encode($children));
		}

	}

	protected function _executeHandler($listener, $event, $eventArgs){
		ob_start();
		$this->handler->handleEvent($listener, $event, $eventArgs);
		$content=trim(ob_get_contents());
		if(!empty($content)){
			$this->_log($content);
		}
		ob_end_clean();
	}

	protected function _log($message){
		$log=$this->logger;
		$log($message);
	}

}
