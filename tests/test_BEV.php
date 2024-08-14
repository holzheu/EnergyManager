<?php
require_once __DIR__ . '/../EnergyManager/autoload.php';
require_once __DIR__ . "/../EnergyManager/secrets.php";


$bev = new \EnergyManager\BEV\BevDummy();
$pv = new \EnergyManager\PV\PvDummy();
$price = new \EnergyManager\Price\PriceAwattar();
$bev->refresh();
echo $bev->getStatus();
$price->refresh();
$pv->refresh();
$bev->plan($pv, $price);
print_r($bev->getPlan());



$bev = new \EnergyManager\BEV\BevDIY([
    "ip"=>BEV_DIV_ip,
    'kwh'=>17.9,
    'kw'=>2.2
    ]);
$bev->refresh();
echo $bev->getStatus();

