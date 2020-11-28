<?php
namespace asyncevent;

interface EventEmitter
{

	public function fireEvent($event, $data);
	public function fireEventSync($event, $data);

	public function scheduleEvent($event, $eventArgs, $secondsFromNow);
	public function scheduleEventInterval($event, $eventArgs, $intervalSeconds);
	public function throttleEvent($event, $eventArgs, $throttleOptions, $secondsFromNow=0);

	public function hasEvent();
	public function getEvent();
	public function getTrace();
	public function getDepth();

	public function getEventArgs();

	/**
	 * returns an array of key=>value pairs passed to process
	 * @return [type] [description]
	 */
	public function getEnvironmentVariables();



	public function getId();
	public function setId($id);
	
}