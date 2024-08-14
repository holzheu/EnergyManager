<?php

require_once __DIR__ . '/../EnergyManager/autoload.php';

$temp = new \EnergyManager\Temp\TempOpenMeteo([
    "latitude" => 50.8333,
    "longitude" => 11.8367
]);

$temp->refresh();
print_r($temp->getDaily());
print_r($temp->getHourly());