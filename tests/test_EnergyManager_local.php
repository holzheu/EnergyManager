#!/usr/bin/php
<?php

require_once dirname(__FILE__) . '/../EnergyManager/EnergyManager.php';
require_once dirname(__FILE__) .'/../EnergyManager/secrets.php';

$price = new Price_Awattar();
$hp = new Heatpump_quadratic([
    "lin_coef" => 0.017678,
    "quad_coef" => 0.002755
]);
$temp = new Temp_OpenMeteo([
    "latitude" => OpenMeteo_latitude,
    "longitude" => OpenMeteo_longitude
]);
$bev = new BEV_DIY([
    "ip" => BEV_DIV_ip,
    'kwh' => 17.9,
    'kw' => 2.2
]);

$pv = new PV_Solarprognose([
    'access_token' => Solarprognose_access_token,
    'plant_id' => Solarprognose_plant_id,
    'factor' => 2
]);
$house = new House_constant(['kwh_per_day' => 10]);
$bat = new Battery_Kostal_Plenticore_Plus(['ip' => Kostal_Plenticore_Plus_ip]);



$manager = new EnergyManager($pv, $bat, $price, $house, $bev, $hp, $temp);
echo $manager->plan();
print_r($manager->get_planning_info());
