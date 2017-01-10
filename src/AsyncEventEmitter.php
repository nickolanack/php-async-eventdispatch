<?php
namespace asyncevent;

class AsyncEventEmitter implements EventEmitter
{

	
	protected $event;
	protected $eventArgs;

	protected $trace;

	protected $cmd;

	protected $env;
	protected $envKeys=array();

	protected $environment;
	
	public function __construct($config){
	



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

		$this->trace=getmypid();
		$this->depth=0;

		if (key_exists('TERM', $_SERVER)) {

			

			$longOpts=array(
						'event:',
						'eventArgs:',
						'trace:', 
						'depth:',
						'envKeys:',
						
			);
			$args = getopt('',$longOpts);

			

			if (key_exists('event', $args)) {

				echo json_encode($args)."\n";

				$this->event = $args['event'];

				if (key_exists('eventArgs', $args)) {
					$this->eventArgs = json_decode($args['eventArgs']);
				}
				if (key_exists('envKeys', $args)) {
					$this->envKeys = json_decode($args['envKeys']);
				}
				if (key_exists('depth', $args)) {
					$this->depth = (int) $args['depth'];
					if($this->depth>=6){
						throw new Exception('Async AsyncEventEmitter reached nested event limit: '.$this->depth);
					}
				}

				if (key_exists('trace', $args)) {
					$this->trace = $args['trace'].':'.getmypid();
				}

				if(!empty($this->envKeys)){

					$longOptsEnv=array_map(function($k){
						return $k.':';
					}, $this->envKeys);
					$envArgs=array_diff_key(getopt('', array_merge($longOpts, $longOptsEnv)), $args);

					if(!empty(array_diff($this->envKeys, array_keys($envArgs)))){
						throw new \Exception('Expected environment args matching '.json_encode($longOptsEnv));
					}
					echo json_encode($envArgs)."\n";
					$this->env=$envArgs;

				}

			}	

		}

	}

	public function fireEvent($event, $eventArgs){

		

		$bg=' &';
		$cmd=$this->_cmd().$this->_args($event, $eventArgs).$this->_out().$bg;
		system($cmd, $error);


	}

	public function fireEventSync($event, $data){

		$cmd=$this->_cmd().$this->_args($event, $eventArgs).$this->_out();
		system($cmd, $error);


	}

	
	public function getShellEventCommand($event, $eventArgs){
		return $this->_cmd().$this->_args($event, $eventArgs);
	}

	protected function _cmd(){
		return $this->cmd;
	}
	protected function _args($event, $eventArgs){

		$argString=' --event ' . escapeshellarg($event) .
		' --eventArgs ' . escapeshellarg(json_encode($eventArgs)) .
		' --trace ' . escapeshellarg($this->trace. '->' . $event) .
		' --depth ' . ($this->depth + 1);

		$environment=$this->environment;
		$envKeys=array();
		$envString='';
		if($environment){
			foreach($environment() as $key=>$value){
				$envString.=' --'.$key.' '.escapeshellarg($value);
				$envKeys[]=$key;
			}
		}

		$argString.=' --envKeys '.escapeshellarg(json_encode($envKeys));
		

		return $argString.$envString;
	}
	protected function _out(){

		//return ' 2>&1';

		$logFile = __DIR__  . '/.event.log';
		return ' >> ' . $logFile . ' 2>&1';

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