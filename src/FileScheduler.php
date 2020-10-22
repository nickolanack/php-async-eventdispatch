<?php

namespace asyncevent;

class FileScheduler extends Scheduler {

	protected $dir;
	protected $fp=null;
	

	public function __construct($folder) {

		if(strpos($folder, '.json')){
			$folder=dirname($folder);
		}

		if(is_dir($folder)){
			$this->dir=$folder;
			return;
		}




		if(!(is_file($folder)||is_dir($folder))){
			throw new \Exception('Path does not exist: '.$folder);
		}

		if(!is_dir(dirname($folder))){
			throw new \Exception('Not a valid folder: '.$folder);
		}

		$this->dir=dirname($folder);

	}

	public function createSchedule($scheduleData, $token){

		while(file_exists($file=$this->getScheduleFile($token))){}
		file_put_contents($file, 
			json_encode($scheduleData, JSON_PRETTY_PRINT));

		return $file;
	}


	protected function getLastThrottledExecution($eventName){
		$throttle = $this->dir . '/.throttle.' .$eventName.'.last';
		if(!file_exists($throttle)){
			return -1;
		}
		return filemtime($throttle);
	}
	protected function markThrottledExecution($eventName){
		$throttle = $this->dir . '/.throttle.' .$eventName.'.last';
		touch($throttle);
	}

	
	protected function getScheduleFile($token){
		return $this->dir.'/.'.$token.'.json';
	}


	public function queue($scheduleName) {

		$file = $scheduleName;

		$queue = dirname($file) . '/.queue' . microtime() . '-' . substr(md5(time() . rand(1000, 9999)), 0, 10) . '.json';
		if(file_exists($file)){
			rename($file, $queue);
		}
		return $this;

	}


	protected function getSchedules(){
		


		
		return array_values(
			array_filter(scandir($this->dir), function($file){

				if(strpos($file, '.schedule')===0){
					$this->queue($this->dir.'/'.$file);
					return false;
				}

				if(strpos($file, '.queue')===0){
					return strpos($file, '.lock')===false&&(!file_exists($file.'.lock'));
				}
				return false;
			})
		);
			
		

	}
	protected function lockEvent($scheduleName){
		$file = $scheduleName;
		if(file_exists($file.'.lock')){
			return false;
		}
		if(!file_put_contents($file.'.lock', getmypid())){
			return false;
		}

		$this->fp=fopen($file.'.lock', 'r+');
		if (flock($this->fp, LOCK_EX | LOCK_NB)) {
	        return true;
	    } 

        fclose($this->fp);
        $this->fp=null;
        return false;
	    

	}

	protected function getScheduleData($scheduleName) {

		$file = $scheduleName;
		if(!file_exists($file)){
			return null;
		}
		$content=file_get_contents($file);
		$schedule = json_decode($content);

		if(is_null($schedule)&&!empty($content)){
			echo getmypid() . ' FileScheduler: Invalid json: '.$scheduleName."\n";
		}

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

		if($this->fp){
			flock($this->fp, LOCK_UN);
			fclose($this->fp);
			$this->fp=null;
		}
		unlink($file.'.lock');
	}


	protected function getRegisteredSchedulerPids(){

		return array_values(
			array_filter(scandir($this->dir), function($file){
				if(strpos($file, '.pid-')===0){
					return true;
				}
				return false;
			})
		);

	}
}