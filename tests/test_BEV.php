<?php

require_once(dirname(__FILE__) ."/../EnergyManager/BEV.php");

$bev = new BEV_Dummy();
$bev->refresh();
echo $bev->getStatus();


$bev = new BEV_DIY([
    "ip"=>"192.168.2.132",
    'kwh'=>17.9,
    'kw'=>2.2
    ]);
$bev->refresh();
echo $bev->getStatus();

