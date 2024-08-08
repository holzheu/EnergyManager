<?php

require_once(dirname(__FILE__) ."/../EnergyManager/BEV.php");
require_once(dirname(__FILE__) ."/../EnergyManager/secrets.php");
$bev = new BEV_Dummy();
$bev->refresh();
echo $bev->getStatus();


$bev = new BEV_DIY([
    "ip"=>BEV_DIV_ip,
    'kwh'=>17.9,
    'kw'=>2.2
    ]);
$bev->refresh();
echo $bev->getStatus();

