<?php
require __DIR__ . "/../EnergyManager/autoload.php";
$time = new \EnergyManager\Time();
$bev = new \EnergyManager\BEV\BevDummy([
    'kwh' => 30,
    'soc' => 80,
    'min_kw' => 1,
    'max_kw' => 3
]);
$bev->setTimeObj($time);

echo $time->get()." - ".$bev->time()."\n";

$time->set(1000);

echo $time->get()." - ".$bev->time()."\n";

$time->set();

echo $time->get()." - ".$bev->time()."\n";
