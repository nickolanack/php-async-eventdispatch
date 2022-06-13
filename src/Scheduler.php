<?php
namespace asyncevent;

abstract class Scheduler {

	protected $autoShutdown = true;
	protected $autoShutdownTimer = 5;
	protected $minProcesses = 1;
	protected $maxProcesses = 4;

	protected $queuedItems = array();
	protected $queuedItemsData = array();

	protected $lastExecutedTime = -1;



	private function _getData($scheduleName){
		if(isset($this->queuedItems[$scheduleName])){
			return $this->queuedItems[$scheduleName];
		}
		return $this->getScheduleData($scheduleName);
	}

	public function run($scheduleName) {

		$scheduleData = $this->_getData($scheduleName);
		if (is_null($scheduleData)) {
			return;
		}

		$this->lockEvent($scheduleName, $scheduleData, function () use ($scheduleName, $scheduleData) {


			if(!isset($scheduleData->schedule)){
				echo 'ERROR: '.$scheduleName.' - '.json_encode($scheduleData).'-'.gettype($scheduleData)."\n";
				return;
			}

			$this->_run($scheduleName, $scheduleData);
		});
		return $this;
	}


	private function _sleep($scheduleName, $scheduleData){
		//echo getmypid() . ': Run: ' .$scheduleName. "\n";

		$time = $scheduleData->schedule->time;

		$secondsFromNow = max($time - time(), 0);
		while ($secondsFromNow > 10) {

			$this->updateProcess($scheduleName, $scheduleData);

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
	}

	private function _throttleOk($scheduleName, $scheduleData){

		return $this->lockThrottle($scheduleData->schedule->name, function()use($scheduleName, $scheduleData){

			if ($this->shouldThrottle($scheduleName, $scheduleData)) {
				$this->throttle($scheduleName);
				return false;
			}


			
			$this->markThrottledExecution($scheduleData->schedule->name);
			usleep(1000);

			return true;

		});

	
	}


	private function _intervalOk($scheduleName, $scheduleData){


		$this->declareInterval($scheduleData->schedule->name, $scheduleData->schedule->token);

		return $this->lockInterval($scheduleData->schedule->name, function()use($scheduleName, $scheduleData){


			if ($this->intervalIsAlreadyRunning($scheduleData->schedule->name, $scheduleData->schedule->token)) {
				$this->remove($scheduleName);
				return false;
			}


			$this->markIntervalExecution($scheduleData->schedule->name, $scheduleData->schedule->token);
			usleep(1000);

			return true;

		});

	}



	private function _handleSpecial($scheduleName, $scheduleData){


		if ($scheduleData->schedule->name === '_clear_all_intervals_') {
			$this->clearAllIntervals();
			return true;
		} 

		if (strpos($scheduleData->schedule->name, '_clear_') === 0) {
			$clearEventName = substr($scheduleData->schedule->name, strlen('_clear_'));
			echo getmypid() . ': Clear Interval: ' . $clearEventName . "\n";
			$this->clearInterval($clearEventName);
			return true;
		} 


		


		return false;


	}

	private function _run($scheduleName, $scheduleData) {


		$this->_sleep($scheduleName, $scheduleData);

		
		if (isset($scheduleData->schedule->throttle)) {
			if(!$this->_throttleOk($scheduleName, $scheduleData)){
				return;
			}
		}

		if (isset($scheduleData->schedule->interval)) {
			if(!$this->_intervalOk($scheduleName, $scheduleData)){
				return;
			}
		}

		$wasSystemEvent=$this->_handleSpecial($scheduleName, $scheduleData);

		if (!$wasSystemEvent){
		
			system($scheduleData->cmd, $returnVar);

			if ($returnVar !== 0) {
				echo getmypid() . ': Event returned nonzero result: ' . $scheduleData->schedule->name . ' - ' . time() . ' (' . $returnVar . ')' . "\n";
			}
		}

		

		$this->lastExecutedTime = time();
		//echo getmypid() . ': Remove Schedule' . "\n";
		$this->remove($scheduleName);
		//echo getmypid() . ': Cleanup' . "\n";

		/**
		 * Note: Not the same as throttle->interval;
		 */
		if (isset($scheduleData->schedule->interval)) {

			/**
			 * edge case. events could start to stack up if the event processing time is longer than the interval
			 */

			$time = $scheduleData->schedule->time + $scheduleData->schedule->interval;
			$now = time();
			if ($time < $now) {
				echo getmypid() . ': Event Overlap ' . "\n";
			}
			$token = $scheduleData->schedule->token;

			$scheduleData->schedule = array_merge(get_object_vars($scheduleData->schedule), array(
				'time' => $time,
			));

			if (!isset($scheduleData->iteration)) {
				$scheduleData->iteration = 0;
			}
			$scheduleData->iteration++;

			//echo getmypid() . ': Queue next interval (' . $scheduleData->iteration . ')' . "\n";
			$this->createSchedule($scheduleData, $token);
		} else {

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
	abstract protected function lockEvent($scheduleName, $scheduleData, $callback);

	abstract protected function registerScheduler();
	abstract protected function unregisterScheduler();
	abstract protected function getRegisteredSchedulerPids();

	abstract protected function getLastThrottledExecution($eventName);
	abstract protected function markThrottledExecution($eventName);

	abstract protected function markIntervalExecution($eventName, $token);
	abstract protected function intervalIsAlreadyRunning($eventName, $token);
	abstract protected function getLastIntervalExecution($eventName);
	abstract protected function clearInterval($eventName);
	abstract protected function clearAllIntervals();

	abstract protected function lockThrottle($eventName, $callback);
	abstract protected function lockInterval($eventName, $callback);
	abstract protected function declareInterval($eventName, $token);


	public function shouldThrottle($scheduleName, $scheduleData) {


		$last = $this->getLastThrottledExecution($scheduleData->schedule->name);

		$throttleTimeLeft = $scheduleData->schedule->throttle->interval - ($scheduleData->schedule->time - $last);

		if ($throttleTimeLeft > 0) {
			//echo getmypid() . ': Throttle: ' .$schedule->schedule->name.' '.$throttleTimeLeft. "\n";
			return true;
		}
	}

	public function throttle($scheduleName) {
		$schedule = $this->_getData($scheduleName);

		if ($schedule->schedule->throttle->reschedule) {
			echo getmypid() . ': Throttle Reshedule not yet supported ' . "\n";
			//throw new \Exception('Implement throttle reschedule');
			//return;
		}

		//echo getmypid() . ': Discard Throttled Schedule: ' .$scheduleName. "\n";
		$this->remove($scheduleName);

	}

	public function shouldRunNow($scheduleName) {

		$schedule = $this->_getData($scheduleName);
		if (is_null($schedule)) {
			return false;
		}

		$time = $schedule->schedule->time;

		$secondsFromNow = max($time - time(), 0);
		return ($secondsFromNow < 2);

	}

	protected function getNextQueuedEvent() {

		if (count($this->queuedItems) == 0) {

			$this->queuedItems = $this->getSchedules();

			//echo getmypid() . ': Refresh shedules: '.count($this->queuedItems).' items'. "\n";

			$this->sortQueuedItems();

			//echo getmypid() . ': Preparing shedules: '.count($this->queuedItems).' items after sort,map'. "\n";

		}

		if (count($this->queuedItems) == 0) {
			return false;
		}



		return array_shift($this->queuedItems);
	}

	protected function sortQueuedItems() {
		foreach ($this->queuedItems as $scheduleName) {
			if (!key_exists($scheduleName, $this->queuedItemsData)) {
				$data = $this->getScheduleData($scheduleName);
				if (!is_null($data)) {

					


						if (isset($data->schedule->interval) && $this->intervalIsAlreadyRunning($data->schedule->name, $data->schedule->token)) {
							
							$this->lockEvent($scheduleName, $data, function()use($scheduleName){
								//echo getmypid() . ': Discard Interval'."\n";
								$this->remove($scheduleName);
							});
							continue;
						}

						if (isset($data->schedule->throttle) && $this->shouldThrottle($scheduleName, $data)) {
							
							$this->lockEvent($scheduleName, $data, function()use($scheduleName){
								$this->throttle($scheduleName);	
							});
							
							continue;
						}

						$this->queuedItemsData[$scheduleName] = $data;
					

				} else {
					//already deleted by another thread
					//echo getmypid() . ': Sorting: Null data: ' . $scheduleName . "\n";
				}
			}
		}

		//echo getmypid() . ': Sorting: map: '.json_encode(array_keys($this->queuedItemsData)). "\n";

		$this->queuedItemsData = array_intersect_key($this->queuedItemsData, array_combine($this->queuedItems, $this->queuedItems));
		$this->queuedItems = array_intersect($this->queuedItems, array_keys($this->queuedItemsData));

		usort($this->queuedItems, function ($a, $b) {
			return $this->queuedItemsData[$a]->schedule->time - $this->queuedItemsData[$b]->schedule->time;
		});

	}

	public function startProcessingLoop() {

		$this->registerScheduler();

		$loops = 0;
		$counter = 0;
		$checked = 0;
		while (true) {
			$loops++;
			while ($scheduleName = $this->getNextQueuedEvent()) {
				$checked++;
				if (!$this->shouldRunNow($scheduleName)) {
					//echo getmypid() . ': Skip: ' .$scheduleName. "\n";
					usleep(50000);
					continue;
				}

				
				$this->run($scheduleName);
				
				usleep(100000);
				$counter++;
			}

			usleep(200000);

			if ($this->autoShutdown && time() - $this->lastExecutedTime > $this->autoShutdownTimer) {
				//echo getmypid() . ': AutoShutdown' . "\n";
				break;
			}

		}

		//echo getmypid() . ': Processed ' . $counter . '/' . $checked . ' items, Ran ' . $loops . ' loops' . "\n";

		$this->unregisterScheduler();

	}

	public function checkProcesses() {

		$this->lastExecutedTime = time();
		if (count($this->getRegisteredSchedulerPids()) < $this->maxProcesses) {
			//echo getmypid() . ': Start Processor' . "\n";
			$this->startProcessingLoop();
			//echo getmypid() . ': Ending Processor' . "\n";
		}

	}

}