<?php

namespace asyncevent\schedulers;

class MemcachedScheduler extends \asyncevent\Scheduler {

	protected $dir;
	protected $fp=null;
	protected $memcached=null;

	protected $namespace='default';

	public function __construct($options='11211', $namespace=null) {

		$port='11211';
		$host='127.0.0.1';

		if(!empty($namespace)){
			$this->namespace=$namespace;
		}

		if(is_string($options)){

			$port=$options;
			if(strpos($port, ':')>=0){


				$parts=explode(':', $port);
				$port=array_pop($parts);
				$host=implode(':', $parts);
			}
		
			
			if(strpos($port, '/')>0){

				if(!empty($namespace)){
					throw new \Exception('Can\'t define namespace twice');
				}

				$parts=explode('/', $port);
				$port=array_shift($parts);
				$namespace=implode('/', $parts);
				$this->namespace=$namespace;
			}
			
		}

		

		if($options instanceof \Memcached){
			$this->memcached=$options;
			return;
		}

		$memcached = new \Memcached();
		if($memcached->addServer($host, intval($port))===false){
			throw new \Exception('Failed to connect to memcached server: ' .$memcached->getResultMessage().' - '.$memcached->getResultCode());
		}
		$this->memcached=$memcached;

	}


	public function encodeEventArgs($eventArgs){

		$name='_args'.rand(1000, 9999).time();
		$this->_store($name, $eventArgs);
		return $name;
	}

	protected function _name($name){
		return preg_replace('/\s+/', '-', $name);
	}
	protected function _store($name, $data){
		$name=$this->_name($name);
		//echo 'store: '.$name."\n";
		$res=$this->memcached->set($this->namespace.$name, [$data, time(), time()]);
		if($res===false){
			throw new \Exception('Failed to store: '.$name.' '. $this->memcached->getResultMessage().' - '.$this->memcached->getResultCode());
		}
	}

	protected function _add($name, $data){
		$name=$this->_name($name);
		//echo 'add: '.$name."\n";
		return $this->memcached->add($this->namespace.$name, [$data, time(), time()]);
	}
	protected function _fetch($name){
		$name=$this->_name($name);
		$data= $this->memcached->get($this->namespace.$name);
		if($data===false){
			throw new \Exception('Failed to fetch: '.$name.' '. $this->memcached->getResultMessage().' - '.$this->memcached->getResultCode());
		}
		$data= json_decode(json_encode($data[0]));
		//echo 'fetch: '.$name.' '.print_r($data, true)."\n";
		return $data;
	}

	protected function _delete($name){
		$name=$this->_name($name);
		//echo 'delete: '.$name."\n";
		$this->memcached->delete($this->namespace.$name);
	}

	protected function _mtime($name){
		$name=$this->_name($name);
		//echo 'mtime: '.$name."\n";
		return $this->memcached->get($this->namespace.$name)[2];
	}

	protected function _exists($name){
		$name=$this->_name($name);
		//echo 'exists: '.$name."\n";
		return $this->memcached->get($this->namespace.$name)!==false;
	}

	protected function _touch($name){
		$name=$this->_name($name);
		//echo 'touch: '.$name."\n";
		$record = $this->memcached->get($this->namespace.$name);

		if($record===false){
			//create if not exists
			$record=[[], time(), time()];
		}

		$record[2]=time();
		$this->memcached->set($this->namespace.$name, $record);
	}

	public function getHandlerArg(){

		$server=$this->memcached->getServerList()[0];
		return $server['host'].':'.$server['port'].'/'.$this->namespace;
	}

	protected function _rename($oldName, $newName){
		$oldName=$this->_name($oldName);
		$newName=$this->_name($newName);

		//echo 'rename: '.$oldName.' '.$newName."\n";
		$record = $this->memcached->get($this->namespace.$oldName);
		$this->memcached->delete($this->namespace.$oldName);

		$record[2]=time();
		$this->memcached->set($this->namespace.$newName, $record);
	}


	protected function _keylist(){
	
		$list = $this->memcached->getAllKeys();

		if($list===false){
			throw new \Exception('Failed to getAllKeys: '. $this->memcached->getResultMessage().' - '.$this->memcached->getResultCode().' '.print_r($this->memcached->getServerList(), true));
		}

		return array_map(function($key){
			return str_replace($this->namespace, '', $key);
		}, array_values(array_filter($list, function($key){
			return strpos($key,$this->namespace)===0;
		})));
	}



	public function decodeEventArgs($eventArgs){
		if(is_string($eventArgs)&&$this->_exists($eventArgs)){
			$name=$eventArgs;
			$eventArgs=$this->_fetch($name);
			$this->_delete($name);
		}
		return $eventArgs;
	}


	public function createSchedule($scheduleData, $token){

		while($this->_exists($name=$this->getScheduleFile($token))){
			throw new \Exception('Already exists: '.$token);
		}
		$this->_store($name, $scheduleData);

		return $name;
	}


	protected function getLastThrottledExecution($eventName){
		$throttle = '.throttle.' .$eventName.'.last';
		if(!$this->_exists($throttle)){
			return -1;
		}
		return $this->_mtime($throttle);
	}
	protected function markThrottledExecution($eventName){
		$throttle = '.throttle.' .$eventName.'.last';
		$this->_touch($throttle);
	}
	protected function getLastIntervalExecution($eventName){
		$schedule = '.interval.' .$eventName.'.schedule';
		if(!$this->_exists($schedule)){
			return -1;
		}
		return $this->_mtime($schedule);
	}

	protected function declareInterval($eventName, $token){
		$schedule = '.interval.' .$eventName.'.schedule';
		if(!$this->_exists($schedule)){
			$this->markIntervalExecution($eventName, $token);
		}
	}

	protected function markIntervalExecution($eventName, $token){
		$schedule = '.interval.' .$eventName.'.schedule';
		$this->_store($schedule, $token);
	}

	protected function intervalIsAlreadyRunning($eventName, $token){
		$interval = '.interval.' .$eventName.'.schedule';
		if(!$this->_exists($interval)){
			return false;
		}
		$activeToken=$this->_fetch($interval);
		if($activeToken===$token){
			return false;
		}
		$scheduleFile=$this->getScheduleFile($activeToken);
		$activeFile=str_replace('.schedule', '.queue', $scheduleFile);

		return $this->_exists($scheduleFile)||$this->_exists($activeFile)||$this->_exists($activeFile.'.lock');

	}

	protected function clearAllIntervals(){

		$intervals= array_values(
			array_filter($this->_keylist(), function($file){
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
		
		$interval = '.interval.' .$eventName.'.schedule';
		if(!$this->_exists($interval)){
			echo getmypid() . ' FileScheduler: Interval not found: '.$eventName."\n";
			return;
		}
		
		$activeToken=$this->_fetch($interval);
		$scheduleFile=$this->getScheduleFile($activeToken);
		$activeFile=str_replace('.schedule', '.queue', $scheduleFile);
		
		if($this->_exists($scheduleFile)){
			$this->_delete($scheduleFile);
		}
		if($this->_exists($activeFile)){
			$this->_delete($activeFile);
		}
		if($this->_exists($interval)){
			$this->_delete($interval);
		}

	}


	
	protected function getScheduleFile($token){
		return '.'.$token.'.json';
	}



	protected function lockInterval($eventName, $callback) {
		$interval = '.interval.' .$eventName.'.schedule';
		return $this->lockFileBlock($interval, $callback);
	}

	protected function lockThrottle($eventName ,$callback) {
		$throttle = '.throttle.' .$eventName.'.last';
		if(!$this->_exists($throttle)){
			$this->_touch($throttle);
		}
		return $this->lockFileBlock($throttle, $callback);
	}


	public function queue($scheduleName) {

		$file = $scheduleName;
		if(!$this->_exists($file)){
			return $this;
		}

		$this->lockFile($scheduleName, function()use($scheduleName){

			$queue=str_replace('.schedule', '.queue', $scheduleName);
			//$queue = dirname($file) . '/.queue' . microtime() . '-' . substr(md5(time() . rand(1000, 9999)), 0, 10) . '.json';
			$this->_rename($scheduleName, $queue);
		});

		return $this;

	}


	protected function getSchedules(){
		


		
		return array_values(
			array_filter($this->_keylist(), function($file){

				if(strpos($file, '.schedule')===0){
					$this->queue($file);
					return false;
				}

				if(strpos($file, '.queue')===0){
					return strpos($file, '.lock')===false&&(!$this->_exists($file.'.lock'));
				}
				return false;
			})
		);
			
		

	}
	protected function lockEvent($scheduleName, $callback){



		$file = $scheduleName;
		if($this->_exists($file.'.lock')){
			return;
		}
		if(!$this->_add($file.'.lock', getmypid())){
			return;
		}

		
		$callback();
	

	
		$this->_delete($file.'.lock');
        
	  
	}

	private function lockFileBlock($file, $callback){
		return $this->lockEvent($file, $callback);
	}


	private function lockFile($file, $callback){
		return $this->lockEvent($file, $callback);
	}

	protected function getScheduleData($scheduleName) {

		if(!$this->_exists($scheduleName)){
			return null;
		}
		$content=$this->_fetch($scheduleName);
		$schedule = $content;
		// $schedule = json_decode($content);

		if(is_null($schedule)&&!empty($content)){
			echo getmypid() . ' FileScheduler: Invalid json: '.$scheduleName."\n";
		}

		return $schedule;
	}

	protected function updateProcess($scheduleName, $scheduleData) {

		if (!$this->_exists($scheduleName)) {
			//in case file gets deleted...
			$this->_store($scheduleName, json_encode($scheduleData, JSON_PRETTY_PRINT));
		}
		$this->_touch($scheduleName);

	}

	protected function registerScheduler() {

		$this->_touch('.pid-' . getmypid());
	
	}

	protected function unregisterScheduler() {

		$this->_delete('.pid-' . getmypid());

	}

	protected function removeSchedule($scheduleName) {
		$file = ''.$scheduleName;

		if(!$this->_exists($file)){
			return;
		}

		$this->_delete($file);

		if($this->_exists($file.'.lock')){
			$this->_delete($file.'.lock');
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
					$this->_delete('.pid-' . $pid);
				}
				continue;
			}

			if(!file_exists('/proc/'.$pid)){
				$this->_delete('.pid-' . $pid);
			}
		}
		
	}


	protected function getRegisteredSchedulerPids(){

		$pids= array_values(
			array_filter($this->_keylist(), function($file){
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