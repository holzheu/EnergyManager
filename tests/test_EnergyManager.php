#!/usr/bin/php
<?php


require_once __DIR__ . '/../EnergyManager/autoload.php';

//create objects
$bat = new \EnergyManager\Battery\BatteryDummy([
    'kwh' => 10,
    'soc' => 80,
    'charge_power' => 1.5
]);
$pv = new \EnergyManager\PV\PvDummy();
$bev = new \EnergyManager\BEV\BevDummy([
    'kwh' => 30,
    'soc' => 80,
    'min_kw' => 1,
    'max_kw' => 3
]);
$house = new \EnergyManager\House\HouseConstant(['kwh_per_day' => 25]);
$house2 = new \EnergyManager\House\HouseConstant(['kwh_per_day' => 5]);
$price = new \EnergyManager\Price\PriceAwattar();
$temp = new \EnergyManager\Temp\TempOpenMeteo([
    "latitude" => 50.8333,
    "longitude" => 11.8367
]);
$hp = new \EnergyManager\Heatpump\HeatpumpQuadratic([
    "lin_coef" => 0.017678,
    "quad_coef" => 0.002755
], $temp);



//create manager
$manager = new \EnergyManager\EnergyManager($pv, $bat, $price, $house, $bev, $hp);
echo $manager->plan();


$manager = new \EnergyManager\EnergyManager($pv, $bat, $price, $house2, $bev, $hp);
echo $manager->plan();

print_r($manager->get_planning_info());


