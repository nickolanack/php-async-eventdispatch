<?php 
namespace asyncevent;

abstract class Scheduler {

	protected $autoShutdown=true;
	protected $autoShutdownTimer=5;
	protected $minProcesses=1;
	protected $maxProcesses=4;

	protected $queuedItems=array();
	protected $queuedItemsData=array();

	protected $lastExecutedTime=-1;


	public function run($scheduleName) {

		if(!$this->lockEvent($scheduleName)){
			//echo getmypid() . ': Locked: ' .$scheduleName. "\n";
			return $this;
		}

		

		$schedule = $this->getScheduleData($scheduleName);

		if(is_null($schedule)){
			//echo getmypid() . ': Invalid/Gone: ' .$scheduleName. "\n";
			return $this;
		}

		//echo getmypid() . ': Run: ' .$scheduleName. "\n";

		$time = $schedule->schedule->time;

		$secondsFromNow = max($time - time(), 0);
		while ($secondsFromNow > 10) {


			$this->updateProcess($scheduleName, $schedule);
			
			if ($secondsFromNow > 20) {
				echo getmypid() . ': Maintainance' . "\n";
			}

			$wait = min(15, $secondsFromNow - 5);
			//echo getmypid() . ': Waiting for ' . $wait . ' seconds (' . $secondsFromNow . ')' . "\n";
			sleep($wait);
			$secondsFromNow = max($time - time(), 0);

		}

		$secondsFromNow = max($time - time(), 0);
		//echo getmypid() . ': Sleep ' . $secondsFromNow . ' seconds (' . $secondsFromNow . ')' . "\n";
		sleep($secondsFromNow);



		if(key_exists('throttle', $schedule->schedule)){
			if($this->shouldThrottle($scheduleName)){
				$this->throttle($scheduleName);
				return;
			}
			
		}



		echo getmypid() . ': Trigger Event ' . time(). ' ('.$schedule->schedule->time.')'."\n";
		
		system($schedule->cmd, $returnVar);

		if($returnVar!==0){
			echo getmypid() . ': Event returned nonzero result ' . time(). ' ('.$returnVar.')'."\n";
		}

		
		if(key_exists('throttle', $schedule->schedule)){
			$this->markThrottledExecution($schedule->schedule->name);
		}


		
		$this->lastExecutedTime=time();
		//echo getmypid() . ': Remove Schedule' . "\n";
		$this->remove($scheduleName);
		//echo getmypid() . ': Cleanup' . "\n";

		if(key_exists('interval', $schedule->schedule)){

			$time=$schedule->schedule->time+$schedule->schedule->interval;
			$token=$schedule->schedule->token;


			/**
			 * edge case. events could start to stack up if the event processing time is longer than the interval
			 */

			$schedule->schedule=array_merge(get_object_vars($schedule->schedule), array(
				'time'=>$time,
			));


			$this->createSchedule($schedule, $token);
		}


		return $this;
	}

	/**
	 * store schedule data and return scheduleName
	 */
	abstract public function createSchedule($scheduleData, $token);

	abstract protected function remove($scheduleName);
	abstract public function queue($scheduleName);
	abstract protected function getScheduleData($scheduleName);
	abstract protected function updateProcess($scheduleName, $scheduleData);

	abstract protected function getSchedules();
	abstract protected function lockEvent($scheduleName);

	abstract protected function registerScheduler();
	abstract protected function unregisterScheduler();
	abstract protected function getRegisteredSchedulerPids();


	abstract protected function getLastThrottledExecution($eventName);
	abstract protected function markThrottledExecution($eventName);


	public function shouldThrottle($scheduleName){
		$schedule = $this->getScheduleData($scheduleName);

		$throttleTimeLeft=$schedule->schedule->throttle->interval-(time()-$this->getLastThrottledExecution($schedule->schedule->name));

		if($throttleTimeLeft>0){
			echo getmypid() . ': Throttle: ' .$schedule->schedule->name.' '.$throttleTimeLeft. "\n";
			return true;
		}
	}

	public function throttle($scheduleName){
		$schedule = $this->getScheduleData($scheduleName);


		if($schedule->schedule->throttle->reschedule){
			echo getmypid() . ': Throttle Reshedule not yet supported '."\n";
			//throw new \Exception('Implement throttle reschedule');
			//return;
		}


		echo getmypid() . ': Discard Throttled Schedule: ' .$scheduleName. "\n";
		$this->remove($scheduleName);

	}

	public function shouldRunNow($scheduleName){

		$schedule = $this->getScheduleData($scheduleName);
		if(is_null($schedule)){
			return false;
		}
		$time = $schedule->schedule->time;

		$secondsFromNow = max($time - time(), 0);
		return ($secondsFromNow < 2);
	
	}


	protected function getNextQueuedEvent(){
		


		if(count($this->queuedItems)==0){
				


			$this->queuedItems=$this->getSchedules();

			//echo getmypid() . ': Refresh shedules: '.count($this->queuedItems).' items'. "\n";

			$this->sortQueuedItems();

			//echo getmypid() . ': Preparing shedules: '.count($this->queuedItems).' items after sort,map'. "\n";

		}

		if(count($this->queuedItems)==0){
			return false;
		}

		return array_shift($this->queuedItems);
	}


	protected function sortQueuedItems(){
		foreach ($this->queuedItems as $scheduleName) {
			if(!key_exists($scheduleName , $this->queuedItemsData)){
				$data=$this->getScheduleData($scheduleName);
				if(!is_null($data)){
					$this->queuedItemsData[$scheduleName]=$data;

				}else{
					echo getmypid() . ': Sorting: Null data: '.$scheduleName. "\n";
				}
			}	
		}

		//echo getmypid() . ': Sorting: map: '.json_encode(array_keys($this->queuedItemsData)). "\n";

		$this->queuedItemsData=array_intersect_key($this->queuedItemsData, array_combine($this->queuedItems, $this->queuedItems));
		$this->queuedItems=array_intersect($this->queuedItems, array_keys($this->queuedItemsData));

		usort($this->queuedItems, function($a, $b){
			return $this->queuedItemsData[$a]->schedule->time-$this->queuedItemsData[$b]->schedule->time;
		});
			
	}
	

	public function startProcessingLoop(){

		$this->registerScheduler();

		$loops=0;
		$counter=0;
		$checked=0;
		while(true){
			$loops++;
			while($scheduleName=$this->getNextQueuedEvent()){
				$checked++;
				if(!$this->shouldRunNow($scheduleName)){
					//echo getmypid() . ': Skip: ' .$scheduleName. "\n";
					usleep(50000);
					continue;
				}

									
				$this->run($scheduleName);
				usleep(100000);
				$counter++;
			}


			
			usleep(200000);

			if($this->autoShutdown&&time()-$this->lastExecutedTime>$this->autoShutdownTimer){
				echo getmypid() . ': AutoShutdown' . "\n";
				break;
			}

		}

		echo getmypid() . ': Processed '.$counter. '/'.$checked.' items, Ran '. $loops.' loops' . "\n";

		$this->unregisterScheduler();
		
	}


	public function checkProcesses(){


		$this->lastExecutedTime=time();
		if(count($this->getRegisteredSchedulerPids())<$this->maxProcesses){
			echo getmypid() . ': Start Processor' . "\n";
			$this->startProcessingLoop();
			echo getmypid() . ': Ending Processor' . "\n";
		}


	}

}