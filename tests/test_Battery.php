<?php

require_once(dirname(__FILE__) ."/../EnergyManager/Battery.php");

$bat = new Battery_Dummy([
    'kwh'=>10,
    'soc'=>50
]);
echo $bat->getStatus();

$bat = new Battery_Kostal_Plenticore_Plus(['ip' => '192.168.2.78']);

$bat->refresh();
echo $bat->getStatus();

