#!/usr/bin/php
<?php

require_once dirname(__FILE__) . '/../EnergyManager/EnergyManager.php';

//create objects
$bat = new Battery_Dummy([
    'kwh' => 10,
    'soc' => 50,
    'charge_power' => 1.5
]);
$pv = new PV_Dummy();
$bev = new BEV_Dummy([
    'kwh' => 30,
    'soc' => 30,
    'min_kw' => 1,
    'max_kw' => 3
]);
$house = new House_constant(['kwh_per_day' => 25]);
$price = new Price_Awattar();
$hp = new Heatpump_quadratic([
    "lin_coef" => 0.017678,
    "quad_coef" => 0.002755
]);
$temp = new Temp_OpenMeteo([
    "latitude" => 50.0333,
    "longitude" => 11.5667
]);



$manager = new EnergyManager($pv, $bat, $price, $house, $bev, $hp, $temp);

$dt= new DateTime();
$dt->setTimestamp(floor($dt->getTimestamp() /3600)*3600);

for( $i = 0; $i < 12; $i++ ){
    echo $manager->plan($dt->getTimestamp());
    $bat = new Battery_Dummy([
        'kwh' => 10,
        'soc' => $manager->get_planning_info($dt->getTimestamp())['Bat'],
        'charge_power' => 1.5
    ]);
    $manager = new EnergyManager($pv, $bat, $price, $house, $bev, $hp, $temp);

    
    $dt->modify("+1 hour");
}



