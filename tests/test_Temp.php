<?php

require_once(dirname(__FILE__) ."/../EnergyManager/Temp.php");

$temp = new Temp_OpenMeteo([
    "latitude" => 50.0333,
    "longitude" => 11.5667
]);

$temp->refresh();
print_r($temp->getDaily());
print_r($temp->getHourly());