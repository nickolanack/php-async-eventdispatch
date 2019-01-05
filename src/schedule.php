<?php


$longOpts=array(
	'schedule:'
);
$args = getopt('',$longOpts);


$file=$args['schedule'];
$schedule=json_decode(file_get_contents($file));
$time=$schedule->schedule->time;

$secondsFromNow=max($time-time(), 0);
while($secondsFromNow>10){

	if(!file_exists($file)){
		//in case file gets deleted...
		file_put_contents($file, json_encode($schedule, JSON_PRETTY_PRINT));
	}
	touch($file);
	if($secondsFromNow>20){
		echo getmypid().': Maintainance'."\n";
	}


	$wait=min(15, $secondsFromNow-5);
	echo getmypid().': Waiting for '.$wait.' seconds ('.$secondsFromNow.')'."\n";
	sleep($wait);
	$secondsFromNow=max($time-time(), 0);

}

$secondsFromNow=max($time-time(), 0);
echo getmypid().': Sleep '.$secondsFromNow.' seconds ('.$secondsFromNow.')'."\n";
sleep($secondsFromNow);
echo getmypid().': Trigger Event'."\n";
system($schedule->cmd);
echo getmypid().': Remove File'."\n";
unlink($file);

echo getmypid().': Cleanup'."\n";
