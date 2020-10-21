<?php
namespace asyncevent;

class EventDispatcher
{
	
	protected $emitter;
	protected $handler;
	protected $logger;

	protected $canFork=false;

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
			if(is_string($this->logger)){
				$path=$config['log'];
				$this->logger=function($message)use($path){
					file_put_contents($path, getmypid().' '.date_format(date_create(), 'Y-m-d H:i:s') . ' ' . $message . "\n", FILE_APPEND);
				};
			}
		}
		if(!$this->logger){
			$this->logger=function($message){
				echo getmypid().' '.date_format(date_create(), 'Y-m-d H:i:s') . ' ' . $message . "\n";
			};
		}

		if(key_exists('fork', $config)){
			$this->canFork=!!$config['fork'];	
		}
		

	}


	public function emit($event, $eventArgs){

		$this->_log('Begin emitting: '.$event.'('.json_encode($eventArgs).')');
		ob_start();

		$this->emitter->fireEvent($event, $eventArgs);

		$content=trim(ob_get_contents());
		if(!empty($content)){
			$this->_log($content);
		}
		ob_end_clean();
		$this->_log('Done emitting: '.$event);

	}
	public function emitSync($event, $eventArgs){
		$this->_log('Begin emitting syncrounously: '.$event.'('.json_encode($eventArgs).')');
		ob_start();
		
		$this->emitter->fireEventSync($event, $eventArgs);

		$content=trim(ob_get_contents());
		if(!empty($content)){
			$this->_log($content);
		}
		ob_end_clean();
		$this->_log('Done emitting: '.$event);
	}


	/**
	 * Throttle event allows an event to be scheduled (or emitted immediatly) while 
	 * discarding simultanous events with the same name
	 *
	 * a throttled event is discarded if the last event occurred within {throttleOptions->interval} seconds
	 * instead of discarding the event. it will instead be rescheduled if {throttleOptions->reschedule} is true
	 * 
	 * 
	 */
	public function throttle($event, $eventArgs, $throttleOptions=array(), $secondsFromNow=0){
		
		

		/**
		 * add additional supported throttle options
		 */

		$throttleOptions=array_merge(array(
			'interval'=>30,
			'reschedule'=>false
		),array_intersect_key($throttleOptions, array(
			'interval'=>'',
			'reschedule'=>''
		)));

		$throttleOptions['interval']=max(1, intval($throttleOptions['interval'])); //force int.
		$throttleOptions['reschedule']=!!$throttleOptions['reschedule']; //force boolean



		$this->_log('Begin throttled event: '.$event.'('.json_encode($eventArgs).') ('.json_encode($throttleOptions).')');
		ob_start();

		$this->emitter->throttleEvent($event, $eventArgs, $throttleOptions, $secondsFromNow);

		$content=trim(ob_get_contents());
		if(!empty($content)){
			$this->_log($content);
		}
		ob_end_clean();
		$this->_log('Done throttled event: '.$event);

	}

	public function schedule($event, $eventArgs, $secondsFromNow){
		
		$this->_log('Begin scheduling: '.$event.'('.json_encode($eventArgs).')');
		ob_start();
		
		$this->emitter->scheduleEvent($event, $eventArgs, $secondsFromNow);

		$content=trim(ob_get_contents());
		if(!empty($content)){
			$this->_log($content);
		}
		ob_end_clean();
		$this->_log('Done emitting: '.$event);

	}


	public function getId(){
		return $this->emitter->getId();
	}
	public function setId($id){
		$this->emitter->setId($id);
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

	public function handleEvent($handler=null){

		if(!($handler instanceof Handler)){
			throw new \Exception('Expected event handler to implement Handler');
		}

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

		$this->_log('Listeners: '.count($listeners));





		$this->_handleEvent($listeners, $event, $eventArgs);

		$this->_log('Finished handling event: '.$event);

	}  


	protected function _handleEvent($listeners, $event, $eventArgs){

		$i=1;
		$children=array();
		foreach($listeners as $listener){


			$pid=-1;
			if($this->canFork&&function_exists('pcntl_fork')){
				$pid = pcntl_fork();
			}
			
			if ($pid == -1) {
			    //$this->_log('Unable to fork');
			    $this->_executeHandler($listener, $event, $eventArgs);

			} else if ($pid) {
				$children[]=$pid;
			     $this->_log('Forked parent->'.$pid);
			     
			    


			    
			} else {

				$this->_log('Child: #'.$i);
			    $this->_executeHandler($listener, $event, $eventArgs);
			    $this->_log('Child #'.$i.' finished');

			    //need to return here or the child will also fork and execute on the next loop
			    while(true){
			    	sleep(1);
			    }

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
