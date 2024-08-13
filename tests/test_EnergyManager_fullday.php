#!/usr/bin/php
<?php

require_once __DIR__ . '/../EnergyManager/EnergyManager.php';

//create objects
$bat = new \EnergyManager\Battery\BatteryDummy([
    'kwh' => 10,
    'soc' => 50,
    'charge_power' => 1.5
]);
$pv = new \EnergyManager\PV\PvDummy();
$bev = new \EnergyManager\BEV\BevDummy([
    'kwh' => 30,
    'soc' => 30,
    'min_kw' => 1,
    'max_kw' => 3
]);
$house = new \EnergyManager\House\HouseConstant(['kwh_per_day' => 25]);
$price = new \EnergyManager\Price\PriceAwattar();
$hp = new \EnergyManager\Heatpump\HeatpumpQuadratic([
    "lin_coef" => 0.017678,
    "quad_coef" => 0.002755
]);
$temp = new \EnergyManager\Temp\TempOpenMeteo([
    "latitude" => 50.8333,
    "longitude" => 11.8367
]);



$manager = new \EnergyManager\EnergyManager($pv, $bat, $price, $house, $bev, $hp, $temp);

$dt= new DateTime();
$dt->setTimestamp(floor($dt->getTimestamp() /3600)*3600);

for( $i = 0; $i < 12; $i++ ){
    echo $manager->plan($dt->getTimestamp());
    $bat = new \EnergyManager\Battery\BatteryDummy([
        'kwh' => 10,
        'soc' => $manager->get_planning_info($dt->getTimestamp())['Bat'],
        'charge_power' => 1.5
    ]);
    $manager = new \EnergyManager\EnergyManager($pv, $bat, $price, $house, $bev, $hp, $temp);

    
    $dt->modify("+1 hour");
}



