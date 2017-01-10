<?php
namespace asyncevent;

interface EventEmitter
{

	public function fireEvent($event, $data);


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

}