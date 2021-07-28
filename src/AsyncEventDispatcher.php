<?php
namespace asyncevent;

class AsyncEventDispatcher extends EventDispatcher
{

	public function __construct($config){

		if(!isset($config['command'])){
			$config['command']='php '.debug_backtrace()[0]['file'];
		}
		
		parent::__construct(array_merge($config, array('eventEmitter'=>new AsyncEventEmitter($config))));

	}

	public function handleEvent($config=null){

		if($config instanceof Handler){
			parent::handleEvent($config);
		}else{
			parent::handleEvent(new EventHandler($config));
		}
	}





	/**
	 * this is a helper method, in case you want to run some complicated background proccesses
	 * here is an example
	 *
	 *	shell_exec('(     (touch '.$lock.') ; '.
	 *		'('.$dispatcher->getShellEventCommand('onTranscodeStart', array('in'=>$in, 'out'=>$out, 'log'=>$log)).') ;'
	 *		'(ffmpeg -i '.$in.' -o '.$out.' > '.$log.' 2>&1) ; '.
	 *		'('.$dispatcher->getShellEventCommand('onTranscodeEnd', array('in'=>$in, 'out'=>$out)).') ;'
	 *		'(rm processing.lock)     ) >/dev/null 2>&1 &'
	 *	);
	 * 
	 */
	public function getShellEventCommand($event, $eventArgs){
		return $this->emitter->getShellEventCommand($event, $eventArgs);
	}
}