<?php
namespace asyncevent;

class AsyncEventEmitter implements EventEmitter
{

	
	protected $event;
	protected $eventArgs;

	protected $trace;

	protected $cmd;

	protected $env;
	protected $logPath;
	
	protected $id;
	protected $counter=0;
	protected $depth=0;

	protected $environment;



	protected $handlerArg;
	protected $handler=FileScheduler::class;

	private $_last=-1;
	
	public function __construct($config){
	

		$this->logPath = __DIR__  . '/.event.log';
		$this->handlerArg = __DIR__ ;

		if(is_object($config)){
			$config=get_object_vars($config);
		}

		if(!key_exists('command', $config)){
			throw new \Exception('AsyncEventEmitter requires command parameter');
		}


		$this->cmd=$config['command'];
		if(key_exists('getEnvironment', $config)){
			$this->environment=$config['getEnvironment'];
		}

		if(key_exists('log', $config)&&is_string($config['log'])){
			$this->logPath=$config['log'];
		}

		if(key_exists('schedule', $config)/*&&is_string($config['schedule'])*/){
			$this->handlerArg=$config['schedule'];
		}
			
		if(key_exists('handler', $config)&&is_string($config['handler'])){
			$this->handler=$config['handler'];
		}

		$this->trace=$this->getId();
		$this->depth=0;

		if ($this->isCli()) {



			$args=$this->eventArgs();




			if (!empty($args)) {

				//echo json_encode($args)."\n";

				$this->event = $args->name;

				$handlerClass=$this->handler;
				$handler=new $handlerClass($this->handlerArg);
				$eventArgsDecoded=$handler->decodeEventArgs($args->arguments);
				$this->eventArgs = $eventArgsDecoded;

			
				
				$this->depth = (int) $args->depth;
				if($this->depth>=6){
					throw new \Exception('Async AsyncEventEmitter reached nested event limit:'.$this->depth.'  for event: '.$this->event);
				}
				

				$this->trace = $args->trace.':'.$this->getId();
	

				$this->env=$args->environment;

			}	

		}

	}


	public function getId(){
		if(is_null($this->id)){
			$this->id=getmypid().'-'.((int)(microtime(true)*1000000));
			
		}
		return $this->id.'-'.$this->counter;
	}
	public function setId($id){
		$this->id=$id;
	}


	protected function isCli(){
		return php_sapi_name() === 'cli';
	}
	protected function eventArgs(){
		if ($this->isCli()) {



			if(key_exists('argv', $_SERVER)){

				$argv=$_SERVER['argv'];

				$i=array_search('--event', $_SERVER['argv']);
				if($i!==false){

					$argi=$i+1;
					if($argi>=count($argv)){
						throw new \Exception('Expected event args to follow `--event` arg ('.$i.')'.print_r($argv, true));
					}

					$event=$argv[$argi];
					return json_decode($event);
				}
			}
		}

		return null;
	}

	public function fireEvent($event, $eventArgs){

		

		// $bg=' &';
		// $cmd=$this->getShellEventCommand($event, $eventArgs).$this->_out().$bg;
		// system($cmd, $error);


		$this->scheduleEvent($event, $eventArgs, 0);


	}

	public function fireEventSync($event, $eventArgs){

		$cmd=$this->getShellEventCommand($event, $eventArgs).$this->_out();
		system($cmd, $error);


		$this->counter++;
	}


	protected function getScheduleToken(){
		return 'schedule'.microtime().'-'.substr(md5(time().rand(1000, 9999)), 0, 10);
	}

	public function scheduleEvent($event, $eventArgs, $secondsFromNow){

		$now=time();
		$time=$now+$secondsFromNow;

		

		$handlerClass=$this->handler;
		$handler=new $handlerClass($this->handlerArg);
		$token=$this->getScheduleToken();

		$schedule=$handler->createSchedule(array(
				'schedule'=>array(
					'name'=>$event,
					'dispatched'=>$now,
					'time'=>$time,
					'token'=>$token
				),
				'cmd'=>$this->getShellEventCommand($event, $eventArgs).$this->_out().' &'

			), $token);


		$this->_trigger($schedule);
		

	}

	public function scheduleEventInterval($event, $eventArgs, $secondsFromNow){

		$now=time();
		$time=$now+$secondsFromNow;

		

		$handlerClass=$this->handler;
		$handler=new $handlerClass($this->handlerArg);
		$token=$this->getScheduleToken();

		$schedule=$handler->createSchedule(array(
				'schedule'=>array(
					'name'=>$event,
					'dispatched'=>$now,
					'time'=>$time,
					'token'=>$token,
					'interval'=> $secondsFromNow
				),
				'cmd'=>$this->getShellEventCommand($event, $eventArgs).$this->_out().' &'

			), $token);


		$this->_trigger($schedule);
		

	}

	private function _trigger($schedule){

		if($this->getDepth()>0){
			//schedule must be running already, this could get out of control
			return;
		}

		$x=min(5, 0.1*$this->counter*$this->counter*$this->counter);
		if(microtime(true)-$this->_last<$x){
			// for counter values = [0,1,2,3,4,5,6],  $x = [0, 0.1, 0.8, 2.7, 5, 5, 5]
			// if the last event occurred more than than $x seconds ago the emitter will run schedule.php in a separate process
			// schedule.php will create an instance of Scheduler, will queue the task (if it has not already been queued by another 
			// thread) and begin processing available tasks if < {$maxProcesses} schedulers are already running
			// 
			// for testing purposes, calling schedule.php thousands of times can quickly exceed the maximum processes (bash: ulimit -u)
			// but it is not necessary to schedule.php more than a few times until the max threads have been started

			return;
		}


		$keepalive='php '.__DIR__.'/schedule.php'.' --schedule '.escapeshellarg($schedule).' --handler '.escapeshellarg($this->handler);
		$cmd='nice -n 20 /bin/bash -e -c '.escapeshellarg($keepalive);
		system($cmd.$this->_out().' &');
		
		$this->counter++;
		$this->_last=microtime(true);

	}

	public function throttleEvent($event, $eventArgs, $throttleOptions, $secondsFromNow=0){

		$now=time();
		$time=$now+$secondsFromNow;

		

		$handlerClass=$this->handler;
		$handler=new $handlerClass($this->handlerArg);
		$token=$this->getScheduleToken();

		$schedule=$handler->createSchedule(array(
				'schedule'=>array(
					'name'=>$event,
					'dispatched'=>$now,
					'time'=>$time,
					'token'=>$token,
					'throttle'=>$throttleOptions
				),
				'cmd'=>$this->getShellEventCommand($event, $eventArgs).$this->_out().' &'

			), $token);


		$this->_trigger($schedule);


	}

	public function getShellEventCommand($event, $eventArgs){
		$cmd= $this->_cmd().$this->_args($event, $eventArgs);

		return 'nice -n 20 /bin/bash -e -c '.escapeshellarg($cmd);
	}

	protected function _cmd(){
		return $this->cmd;
	}
	protected function _args($event, $eventArgs){

		$environment=$this->environment;
		if($environment instanceof \Closure){
			$environment=$environment();
		}

		if(empty($environment)){
			$environment=array();
		}

		$handlerClass=$this->handler;
		$handler=new $handlerClass($this->handlerArg);
		$eventArgsEscaped=$handler->encodeEventArgs($eventArgs);

		$argString=' --event ' . escapeshellarg(json_encode(array(
			'name'=>$event,
			'arguments'=>$eventArgsEscaped,
			'trace'=>$this->trace. '->' . $event,
			'depth'=>$this->depth + 1,
			'environment'=>$environment
		)));



		return $argString;
	}

	protected function _out(){

		//return ' 2>&1';

		
		return ' >> ' . $this->logPath . ' 2>&1';

	}


	public function hasEvent(){

		return !empty($this->event);

	}

	public function getEvent(){

		return $this->event;

	}

	public function getTrace(){

		return $this->trace;

	}
	public function getDepth(){

		return $this->depth;

	}

	public function getEventArgs(){

		return $this->eventArgs;

	}

	public function getEnvironmentVariables(){


		return $this->env;

	}


}