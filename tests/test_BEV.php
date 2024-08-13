<?php
require_once __DIR__."/../EnergyManager/EnergyManager.php";
require_once __DIR__."/../EnergyManager/secrets.php";


$bev = new \EnergyManager\BEV\BevDummy();
$bev->refresh();
echo $bev->getStatus();


$bev = new \EnergyManager\BEV\BevDIY([
    "ip"=>BEV_DIV_ip,
    'kwh'=>17.9,
    'kw'=>2.2
    ]);
$bev->refresh();
echo $bev->getStatus();

