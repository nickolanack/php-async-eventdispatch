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

	public function getHandlerArg(){
		return realpath($this->dir);
	}


	public function encodeEventArgs($eventArgs){
		$file=tempnam($this->dir, '_args');
		file_put_contents($file, json_encode($eventArgs));
		return $file;
	}

	public function decodeEventArgs($eventArgs){
		if(is_string($eventArgs)&&file_exists($eventArgs)){
			$file=$eventArgs;
			$eventArgs=json_decode(file_get_contents($file));
			unlink($file);
		}
		return $eventArgs;
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
		clearstatcache();
	}
	protected function getLastIntervalExecution($eventName){
		$schedule = $this->dir . '/.interval.' .$eventName.'.schedule';
		if(!file_exists($schedule)){
			return -1;
		}
		return filemtime($schedule);
	}

	protected function declareInterval($eventName, $token){
		$schedule = $this->dir . '/.interval.' .$eventName.'.schedule';
		if(!file_exists($schedule)){
			$this->markIntervalExecution($eventName, $token);
		}
	}

	protected function markIntervalExecution($eventName, $token){
		$schedule = $this->dir . '/.interval.' .$eventName.'.schedule';
		file_put_contents($schedule, $token);
	}

	protected function intervalIsAlreadyRunning($eventName, $token){
		$interval = $this->dir . '/.interval.' .$eventName.'.schedule';
		if(!file_exists($interval)){
			return false;
		}
		$activeToken=file_get_contents($interval);
		if($activeToken===$token){
			return false;
		}
		$scheduleFile=$this->getScheduleFile($activeToken);
		$activeFile=str_replace('.schedule', '.queue', $scheduleFile);

		return file_exists($scheduleFile)||file_exists($activeFile)||file_exists($activeFile.'.lock');

	}

	protected function clearAllIntervals(){

		$intervals= array_values(
			array_filter(scandir($this->dir), function($file){
				if(strpos($file, '.interval.')===0){
					return true;
				}
				return false;
			})
		);

		foreach($intervals as $interval){
			$this->clearInterval(str_replace('.interval.','',str_replace('.schedule','',$interval)));
		}

	}

	protected function clearInterval($eventName){
		
		$interval = $this->dir . '/.interval.' .$eventName.'.schedule';
		if(!file_exists($interval)){
			echo getmypid() . ' FileScheduler: Interval not found: '.$eventName."\n";
			return;
		}
		
		$activeToken=file_get_contents($interval);
		$scheduleFile=$this->getScheduleFile($activeToken);
		$activeFile=str_replace('.schedule', '.queue', $scheduleFile);
		
		if(file_exists($scheduleFile)){
			unlink($scheduleFile);
		}
		if(file_exists($activeFile)){
			unlink($activeFile);
		}
		if(file_exists($interval)){
			unlink($interval);
		}

	}


	
	protected function getScheduleFile($token){
		return $this->dir.'/.'.$token.'.json';
	}



	protected function lockInterval($eventName, $callback) {
		$interval = $this->dir . '/.interval.' .$eventName.'.schedule';
		return $this->lockFileBlock($interval, $callback);
	}

	protected function lockThrottle($eventName ,$callback) {
		$throttle = $this->dir . '/.throttle.' .$eventName.'.last';
		if(!file_exists($throttle)){
			touch($throttle);
		}
		return $this->lockFileBlock($throttle, $callback);
	}


	public function queue($scheduleName) {

		$file = $this->dir.'/'.$scheduleName;
		if(!file_exists($file)){
			return $this;
		}

		$this->lockFile($file, function()use($file, $scheduleName){

			$queue=dirname($file) . '/'.str_replace('.schedule', '.queue', $scheduleName);
			//$queue = dirname($file) . '/.queue' . microtime() . '-' . substr(md5(time() . rand(1000, 9999)), 0, 10) . '.json';
			rename($file, $queue);
		});

		return $this;

	}


	protected function getSchedules(){
		


		
		return array_values(
			array_filter(scandir($this->dir), function($file){

				if(strpos($file, '.schedule')===0){
					$this->queue($file);
					return false;
				}

				if(strpos($file, '.queue')===0){
					return strpos($file, '.lock')===false&&(!file_exists($this->dir.'/'.$file.'.lock'));
				}
				return false;
			})
		);
			
		

	}
	protected function lockEvent($scheduleName, $callback){



		$file = $this->dir.'/'.$scheduleName;
		if(file_exists($file.'.lock')){
			return;
		}
		if(!file_put_contents($file.'.lock', getmypid())){
			return;
		}

		$this->fp=@fopen($file.'.lock', 'r+');

		if(!$this->fp){
			return;
		}

	
		if (flock($this->fp, LOCK_EX | LOCK_NB)) {
	        $callback();
	    } 

	    fclose($this->fp);
        if(file_exists($file.'.lock')){
        	@unlink($file.'.lock');
        }
	  
	}

	private function lockFileBlock($file, $callback){

		if(!file_exists($file)){
			throw new \Exception('File does not exist: '.$file);
		}

		$file_handle = @fopen($file, 'r+');

		if(!$file_handle){
			throw new \Exception('fopen empty file handle: '.$file);
		}

		if(!flock($file_handle, LOCK_EX)){
			fclose($file_handle); //close and unlock the file
			throw new \Exception('Failed to lock file: '.$file);
		}
		$result=$callback();
		fclose($file_handle); //close and unlock the file
		return $result;
	}


	private function lockFile($file, $callback){

		if(!file_exists($file)){
			return;
		}

		$file_handle = @fopen($file, 'r+');

		if(!$file_handle){
			return;
		}

		if(!flock($file_handle, LOCK_EX | LOCK_NB)){
			fclose($file_handle); //close and unlock the file
			return;
		}
		$callback();
		fclose($file_handle); //close and unlock the file

	}

	protected function getScheduleData($scheduleName) {

		$file = $this->dir.'/'.$scheduleName;
		if(!file_exists($file)){
			return null;
		}
		$content=@file_get_contents($file);
		$schedule = json_decode($content);

		if(is_null($schedule)&&!empty($content)){
			echo getmypid() . ' FileScheduler: Invalid json: '.$scheduleName."\n";
		}

		return $schedule;
	}

	protected function updateProcess($scheduleName, $scheduleData) {

		$file = $this->dir.'/'.$scheduleName;
		$schedule = $scheduleData;

		if (!file_exists($file)) {
			//in case file gets deleted...
			file_put_contents($file, json_encode($schedule, JSON_PRETTY_PRINT));
		}
		touch($file);
		clearstatcache();

	}

	protected function registerScheduler() {

		touch($this->dir . '/.pid-' . getmypid());
		clearstatcache();
		//echo getmypid().": Register Scheduler: ".$this->dir.'/.pid-' . getmypid()."\n";

	}

	protected function unregisterScheduler() {

		unlink($this->dir . '/.pid-' . getmypid());
		//echo getmypid().": Unregister Scheduler: ".$this->dir.": ".getmypid();

	}

	protected function removeSchedule($scheduleName) {
		$file = $this->dir.'/'.$scheduleName;

		if(!file_exists($file)){
			return;
		}

		unlink($file);

		if(file_exists($file.'.lock')){
			@unlink($file.'.lock');
		}
	}


	private function checkPid($pids){


		$pid=$pids[rand(0,count($pids)-1)];

		$pid=explode('-', $pid);
		$pid=array_pop($pid);
		$pid=intval($pid);

		if(function_exists('posix_getpgid')){


			if(!posix_getpgid($pid)){
				$this->checkAllPids($pids);
				return;
			}

			return;
		}

		if(!file_exists('/proc/'.$pid)){
			$this->checkAllPids($pids);
		}
	}

	private function checkAllPids($pids){
		foreach($pids as $pid){

			$pid=explode('-', $pid);
			$pid=array_pop($pid);
			$pid=intval($pid);



			if(function_exists('posix_getpgid')){

				if(!posix_getpgid($pid)){
					unlink($this->dir . '/.pid-' . $pid);
				}
				continue;
			}

			if(!file_exists('/proc/'.$pid)){
				unlink($this->dir . '/.pid-' . $pid);
			}
		}
		
	}


	protected function getRegisteredSchedulerPids(){

		$pids= array_values(
			array_filter(scandir($this->dir), function($file){
				if(strpos($file, '.pid-')===0){
					return true;
				}
				return false;
			})
		);

		if(!empty($pids)){
			$this->checkPid($pids);
		}

		return $pids;

	}
}