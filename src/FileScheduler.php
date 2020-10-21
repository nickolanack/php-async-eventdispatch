<?php

namespace asyncevent;

class FileScheduler extends Scheduler {

	protected $dir;
	protected $queuedItems=array();

	public function __construct($folder) {

		if(is_file($folder)&&(!is_dir($folder))){
			$folder=dirname($folder);
		}

		if(!is_dir($folder)){
			throw new \Exception('Not a folder: '.$folder);
		}

		$this->dir = $folder;
	}

	public function createSchedule($scheduleData, $token){

		while(file_exists($file=$this->getScheduleFile($token))){}
		file_put_contents($file, 
			json_encode($scheduleData, JSON_PRETTY_PRINT));

		return $file;
	}


	
	protected function getScheduleFile($token){
		return $this->dir.'/.'.$token.'.json';
	}


	public function queue($scheduleName) {

		$file = $scheduleName;

		$queue = dirname($file) . '/.queue' . microtime() . '-' . substr(md5(time() . rand(1000, 9999)), 0, 10) . '.json';
		rename($file, $queue);
		return $this;

	}


	protected function getNextQueuedEvent(){
		


		if(count($this->queuedItems)==0){
			
			$this->queuedItems=array_values(
				array_filter(scandir($this->dir), function($file){

					if(strpos($file, '.schedule')===0){
						$this->queue($this->dir.'/'.$file);
						return false;
					}

					if(strpos($file, '.queue', )===0){
						return strpos($file, '.lock')===false&&(!file_exists($file.'.lock'));
					}
					return false;
				})
			);
		}

		if(count($this->queuedItems)==0){
			return false;
		}

		return $this->dir.'/'.array_shift($this->queuedItems);
	}
	protected function lockEvent($scheduleName){
		$file = $scheduleName;
		if(file_exists($file.'.lock')){
			return false;
		}
		return file_put_contents($file.'.lock', getmypid())!==false;

	}

	protected function getScheduleData($scheduleName) {

		$file = $scheduleName;
		$schedule = json_decode(file_get_contents($file));
		return $schedule;
	}

	protected function updateProcess($scheduleName, $scheduleData) {

		$file = $scheduleName;
		$schedule = $scheduleData;

		if (!file_exists($file)) {
			//in case file gets deleted...
			file_put_contents($file, json_encode($schedule, JSON_PRETTY_PRINT));
		}
		touch($file);

	}

	protected function registerScheduler() {

		touch($this->dir . '/.pid-' . getmypid());
		echo "Start Scheduler: ".$this->dir.": ".getmypid();

	}

	protected function unregisterScheduler() {

		unlink($this->dir . '/.pid-' . getmypid());
		echo "Stop Scheduler: ".$this->dir.": ".getmypid();

	}

	protected function remove($scheduleName) {
		$file = $scheduleName;
		unlink($file);
		unlink($file.'.lock');
	}


	protected function getRegisteredSchedulerPids(){

		return array_values(
			array_filter(scandir($this->dir), function($file){
				if(strpos($file, '.pid-', )===0){
					return true;
				}
				return false;
			})
		);

	}
}