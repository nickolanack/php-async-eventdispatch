<?php
namespace asyncevent;

class ShellEventEmitter implements EventEmitter
{

	
	protected $event;
	protected $eventArgs;

	protected $trace;

	protected $cmd;
	protected $env;
	protected $handler;

	public function __construct($cmd, $env=array()){
		$this->cmd=$cmd;
		$this->env=$env;


		if (key_exists('TERM', $_SERVER)) {

			$envKeys=array_keys($env);

			$envArgs=getopt('', array_map(function($k){
				return $k.':';
			}, $envKeys));

			$this->env=array_merge($this->env, $envArgs);


			$args = getopt('',
					array(
						'event:',
						'eventArgs:',
						'trace:', 
					));




			if (key_exists('event', $args)) {
				$this->event = $args['event'];

				if (key_exists('eventArgs', $args)) {
					$this->eventArgs = json_decode($args['eventArgs']);
				}

				if (key_exists('trace', $args)) {
					$this->trace = $args['trace'];
				}else{
					$this->trace='';
				}
			}
		}

	}

	public function setShellCommandFn($fn){
		$this->shellCmdFn=$fn;
	}

	public function fireEvent($event, $eventArgs){

		
		$bg='';
		//$bg=' &';
		$cmd=$this->_cmd().$this->_args($event, $eventArgs).$this->_out().$bg;

		echo ($cmd)."\n";
		system($cmd, $success);
		echo $success?'success':'failed';

	}
	protected function _cmd(){
		return $this->cmd;
	}
	protected function _args($event, $eventArgs){

		$argString=' --event ' . escapeshellarg($event) .
		' --eventArgs ' . escapeshellarg(json_encode($eventArgs)) .
		' --trace ' . escapeshellarg( $this->trace. '->' . $event);

		foreach($this->env as $key=>$value){
			$argString.=' --'.$key.' '.$value;
		}

		return $argString;
	}
	protected function _out(){

		return ' 2>&1';

		$logFile = __DIR__  . '/.event.log';
		return ' >> ' . $logFile . ' 2>&1';
	}
	public function fireEventSync($event, $data){

	}

	public function hasEvent(){
		return !empty($this->event);
	}
	public function getEvent(){
		return $this->event;
	}
	public function getEventArgs(){
		return $this->eventArgs;
	}




}