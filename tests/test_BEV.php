<?php
require_once __DIR__ . '/../EnergyManager/autoload.php';
require_once __DIR__ . "/../EnergyManager/secrets.php";


$bev = new \EnergyManager\BEV\BevDummy();
$pv = new \EnergyManager\PV\PvDummy();
$price = new \EnergyManager\Price\PriceAwattar();
$house = new \EnergyManager\House\HouseConstant(['kwh_per_day' => 25]);
$bat = new \EnergyManager\Battery\BatteryDummy([
    'kwh' => 10,
    'soc' => 80,
    'charge_power' => 1.5
]);
$em = new \EnergyManager\EnergyManager($pv, $bat, $price, $house);


$bev->refresh();
echo $bev->getStatus();
$price->refresh();
$pv->refresh();
$bev->plan($em);
print_r($bev->getPlan());



$bev = new \EnergyManager\BEV\BevDIY([
    "ip"=>BEV_DIV_ip,
    'kwh'=>17.9,
    'kw'=>2.2
    ]);
$bev->refresh();
echo $bev->getStatus();

