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


	public function startProcessingLoop(){

		echo getmypid() . ' FileScheduler: Schedule path: '.$this->dir.', '.count($this->getSchedules()).' Items'. "\n";

		parent::startProcessingLoop();
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

		$file = $this->dir.'/'.$scheduleName;

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
	protected function lockEvent($scheduleName){
		$file = $this->dir.'/'.$scheduleName;
		if(file_exists($file.'.lock')){
			return false;
		}
		if(!file_put_contents($file.'.lock', getmypid())){
			return false;
		}

		$this->fp=fopen($file.'.lock', 'r+');
	
		if ($this->fp!==false&&flock($this->fp, LOCK_EX | LOCK_NB)) {
	        return true;
	    } 

	    if($this->fp!==false){
        	fclose($this->fp);
   	 	}
        $this->fp=null;
        return false;
	    

	}

	protected function getScheduleData($scheduleName) {

		$file = $this->dir.'/'.$scheduleName;
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

		$file = $this->dir.'/'.$scheduleName;
		$schedule = $scheduleData;

		if (!file_exists($file)) {
			//in case file gets deleted...
			file_put_contents($file, json_encode($schedule, JSON_PRETTY_PRINT));
		}
		touch($file);

	}

	protected function registerScheduler() {

		touch($this->dir . '/.pid-' . getmypid());
		echo getmypid().": Register Scheduler: ".$this->dir.": ".getmypid();

	}

	protected function unregisterScheduler() {

		unlink($this->dir . '/.pid-' . getmypid());
		echo getmypid().": Unregister Scheduler: ".$this->dir.": ".getmypid();

	}

	protected function remove($scheduleName) {
		$file = $this->dir.'/'.$scheduleName;

		unlink($file);

		if($this->fp){
			flock($this->fp, LOCK_UN);
			fclose($this->fp);
			$this->fp=null;
		}
		unlink($file.'.lock');
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
			$this->checkAllPids();
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

		$this->checkPid($pids);

		return $pids;

	}
}