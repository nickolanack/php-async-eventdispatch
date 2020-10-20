<?php 
namespace asyncevent;

abstract class Scheduler {


	protected $maxProcesses=4;


	public function run($scheduleName) {

		if(!$this->lockEvent($scheduleName)){
			return $this;
		}
		$schedule = $this->getScheduleData($scheduleName);

		$time = $schedule->schedule->time;

		$secondsFromNow = max($time - time(), 0);
		while ($secondsFromNow > 10) {


			$this->updateProcess($scheduleName, $schedule);
			
			if ($secondsFromNow > 20) {
				echo getmypid() . ': Maintainance' . "\n";
			}

			$wait = min(15, $secondsFromNow - 5);
			echo getmypid() . ': Waiting for ' . $wait . ' seconds (' . $secondsFromNow . ')' . "\n";
			sleep($wait);
			$secondsFromNow = max($time - time(), 0);

		}

		$secondsFromNow = max($time - time(), 0);
		echo getmypid() . ': Sleep ' . $secondsFromNow . ' seconds (' . $secondsFromNow . ')' . "\n";
		sleep($secondsFromNow);
		echo getmypid() . ': Trigger Event' . "\n";
		system($schedule->cmd);
		echo getmypid() . ': Remove Schedule' . "\n";
		$this->remove($scheduleName);

		echo getmypid() . ': Cleanup' . "\n";


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

	abstract protected function getNextQueuedEvent();
	abstract protected function lockEvent($scheduleName);

	abstract protected function registerScheduler();
	abstract protected function unregisterScheduler();
	abstract protected function getRegisteredSchedulerPids();


	public function shouldRunNow($scheduleName){

		$schedule = $this->getScheduleData($scheduleName);
		$time = $schedule->schedule->time;

		$secondsFromNow = max($time - time(), 0);
		return ($secondsFromNow < 60);
	
	}

	

	public function startProcessingLoop(){

		$this->registerScheduler();
		echo getmypid() . ': Start Processor' . "\n";
		
		$counter=0;
		while($scheduleName=$this->getNextQueuedEvent()){
			if($this->shouldRunNow($scheduleName)){
				echo getmypid() . ': Run: ' .$scheduleName. "\n";
				$this->run($scheduleName);
				$counter++;
				continue;
			}

			echo getmypid() . ': Skip: ' .$scheduleName. "\n";
		}

		
		$scheduleName=$this->getNextQueuedEvent();
		if($scheduleName){
			echo getmypid() . ': Run: ' .$scheduleName. "\n";
			$this->run($scheduleName);
			$counter++;
		}
		
		echo getmypid() . ': Processed '.$counter. ' items' . "\n";


		$this->unregisterScheduler();
		echo getmypid() . ': Ending Processor' . "\n";
	}


	public function checkProcesses(){

		$pids=$this->getRegisteredSchedulerPids();


		if(count($pids)<$this->maxProcesses){
			
			$this->startProcessingLoop();
	
		}


	}

}