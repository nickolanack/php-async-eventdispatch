<?php

echo getmypid().' Event Runner Test'."\n";

require dirname(__DIR__).'/vendor/autoload.php';



$emitter=new asyncevent\ShellEventEmitter(
	'php '.__FILE__, 
	function(){
		return array(
			'domain'=>'www.example.com',
			'scriptpath'=>'index.php',
			'ip' => '0.0.0.0'
		);
	}
);

$listener=new asyncevent\ExternalListeners(function($event)use(&$dispatcher){

		return array(function($event, $eventArgs)use(&$dispatcher){
			echo getmypid().' I am an event listener (callback function) for event: '.$event.' '.json_encode($eventArgs)."\n";
			if($event=='testEvent'){
				$dispatcher->emit('test2Event', array('hello'=>'world'));
			}
		});
	},
	function($listener, $event, $eventArgs){
		$listener($event, $eventArgs);
	}
):


$dispatcher=new asyncevent\EventDispatcher(
	$emitter, 
	$listener
);



/*
 * 
 */
if($dispatcher->shouldHandleEvent()){

	echo getmypid().' Should Handle'."\n";
	$dispatcher->handleEvent();
}else{
	echo getmypid().' Emit testEvent'."\n";
	$dispatcher->emit('testEvent', array('hello'=>'world'));
}
