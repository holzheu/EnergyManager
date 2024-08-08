<?php

require_once (dirname(__FILE__) . "/../EnergyManager/PV.php");


$pv = new PV_Solarprognose([
    'access_token' => '4ee984c60ff18c765b1141a066176273',
    'plant_id' => 5068,
    'factor' => 2
]);

//$pv->refresh();
//print_r($pv->getProduction());


$pv = new PV_Dummy();
$pv->refresh();
print_r($pv->getProduction());


