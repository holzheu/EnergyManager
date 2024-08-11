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
    'soc' => 70,
    'min_kw' => 1,
    'max_kw' => 3
]);
$house = new House_constant(['kwh_per_day' => 25]);
$house2 = new House_constant(['kwh_per_day' => 5]);
$price = new Price_Awattar();
$hp = new Heatpump_quadratic([
    "lin_coef" => 0.017678,
    "quad_coef" => 0.002755
]);
$temp = new Temp_OpenMeteo([
    "latitude" => 50.0333,
    "longitude" => 11.5667
]);

//create manager
$manager = new EnergyManager($pv, $bat, $price, $house, $bev, $hp, $temp);
echo $manager->plan();


$manager = new EnergyManager($pv, $bat, $price, $house2, $bev, $hp, $temp);
echo $manager->plan();




