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


	protected $environment;
	
	public function __construct($config){
	

		$this->logPath = __DIR__  . '/.event.log';

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
			


		$this->trace=getmypid();
		$this->depth=0;

		if (key_exists('TERM', $_SERVER)) {

			

			$longOpts=array(
						'event:',
			);
			$option = getopt('',$longOpts);

			

			if (key_exists('event', $option)) {

				$args=json_decode($option['event']);


				echo json_encode($args)."\n";

				$this->event = $args->name;
				$this->eventArgs = $args->arguments;

			
				
				$this->depth = (int) $args->depth;
				if($this->depth>=6){
					throw new Exception('Async AsyncEventEmitter reached nested event limit: '.$this->depth);
				}
				

				$this->trace = $args->trace.':'.getmypid();
	

				$this->env=$args->environment;

			}	

		}

	}

	public function fireEvent($event, $eventArgs){

		

		$bg=' &';
		$cmd=$this->getShellEventCommand($event, $eventArgs).$this->_out().$bg;
		system($cmd, $error);


	}

	public function fireEventSync($event, $eventArgs){

		$cmd=$this->getShellEventCommand($event, $eventArgs).$this->_out();
		system($cmd, $error);


	}


	public function scheduleEvent($event, $eventArgs, $secondsFromNow){

		$now=time();
		$time=$now+$secondsFromNow;

		while(file_exists($file=$this->getScheduleFile($token=$this->getScheduleToken()))){}


		file_put_contents($file, 
			json_encode(array(
				'schedule'=>array(
					'dispatched'=>$now,
					'time'=>$time,
					'token'=>$token
				),

				'cmd'=>$this->getShellEventCommand($event, $eventArgs).$this->_out().' &'

			), JSON_PRETTY_PRINT));


		$keepalive='php '.__DIR__.'/schedule.php'.' --schedule '.escapeshellarg($file);
		$cmd='/bin/bash -e -c '.escapeshellarg($keepalive);
		system($keepalive.$this->_out().' &');
		
		

	}
	protected function getScheduleToken(){
		return 'schedule'.substr(md5(time().rand(1000, 9999)), 0, 10);
	}
	protected function getScheduleFile($token){
		return __DIR__.'/.'.$this->getScheduleToken().'.json';
	}
	
	public function getShellEventCommand($event, $eventArgs){
		$cmd= $this->_cmd().$this->_args($event, $eventArgs);

		return '/bin/bash -e -c '.escapeshellarg($cmd);
	}

	protected function _cmd(){
		return $this->cmd;
	}
	protected function _args($event, $eventArgs){

		$environment=$this->environment;

		$argString=' --event ' . escapeshellarg(json_encode(array(
			'name'=>$event,
			'arguments'=>$eventArgs,
			'trace'=>$this->trace. '->' . $event,
			'depth'=>$this->depth + 1,
			'environment'=>$environment()
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