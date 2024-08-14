<?php

require_once __DIR__ . '/../EnergyManager/autoload.php';

$bat = new \EnergyManager\Battery\BatteryDummy([
    'kwh' => 10,
    'soc' => 50
]);
echo $bat->getStatus();

$bat = new \EnergyManager\Battery\BatteryKostalByd(['ip' => '192.168.2.78']);

$bat->refresh();
echo $bat->getStatus();

