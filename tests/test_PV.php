<?php

require_once __DIR__."/../EnergyManager/EnergyManager.php";
require_once __DIR__."/../EnergyManager/secrets.php";


$pv = new \EnergyManager\PV\PvSolarprognose([
    'access_token' => Solarprognose_access_token,
    'plant_id' => Solarprognose_plant_id,
    'factor' => 2
]);

//$pv->refresh();
//print_r($pv->getProduction());


$pv = new \EnergyManager\PV\PvDummy();
$pv->refresh();
print_r($pv->getProduction());


