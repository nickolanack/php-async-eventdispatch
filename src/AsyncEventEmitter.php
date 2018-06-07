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
			


		$this->trace=$this->getId();
		$this->depth=0;

		if ($this->isCli()) {



			$args=$this->eventArgs();




			if (!empty($args)) {

				//echo json_encode($args)."\n";

				$this->event = $args->name;
				$this->eventArgs = $args->arguments;

			
				
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
		return key_exists('TERM', $_SERVER)||php_sapi_name() === 'cli';
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
		
		$this->counter++;
		

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
		if($environment instanceof \Closure){
			$environment=$environment();
		}

		if(empty($environment)){
			$environment=array();
		}

		$argString=' --event ' . escapeshellarg(json_encode(array(
			'name'=>$event,
			'arguments'=>$eventArgs,
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