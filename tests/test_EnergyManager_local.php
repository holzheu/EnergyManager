#!/usr/bin/php
<?php

require_once dirname(__FILE__) . '/../EnergyManager/EnergyManager.php';

// Defines constants used to configure objects
require_once dirname(__FILE__) . '/../EnergyManager/secrets.php';

//create objects
$bat = new \EnergyManager\Battery\BatteryKostalByd(['ip' => Kostal_Plenticore_Plus_ip]);
$pv = new \EnergyManager\PV\PvDummy();
$bev = new \EnergyManager\BEV\BevDIY([
    "ip" => BEV_DIV_ip,
    'kwh' => 17.9,
    'kw' => 2.2
]);
$house = new \EnergyManager\House\HouseConstant(['kwh_per_day' => 10]);
$price = new \EnergyManager\Price\PriceAwattar();
$hp = new \EnergyManager\Heatpump\HeatpumpQuadratic([
    "lin_coef" => 0.017678,
    "quad_coef" => 0.002755
]);
$temp = new \EnergyManager\Temp\TempOpenMeteo([
    "latitude" => OpenMeteo_latitude,
    "longitude" => OpenMeteo_longitude
]);

//Create Manager 
$manager = new \EnergyManager\EnergyManager($pv, $bat, $price, $house, $bev, $hp, $temp);
echo $manager->plan();
print_r($manager->get_planning_info());
