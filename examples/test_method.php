#!/usr/bin/php
<?php

require_once dirname(__FILE__) . '../EnergyManager/EnergyManager.php';


$manager = new EnergyManager();
$manager->set_pv([
    'access_token' => 'secret',
    'plant_id' => 53973,
    'factor' => 2
]);
$manager->set_battery(['ip' => '192.168.2.100']);
$manager->set_temp([
    "latitude" => 51.0451,
    "longitude" => 12.5477
]);
$manager->set_heatpump([
    "lin_coef" => 0.017678,
    "quad_coef" => 0.002755
]);
$manager->set_house(["consumption"=>10/24]);
$manager->set_bev(["ip"=>"192.168.2.101"]);



$manager->verbous = true;
$manager->cached_data = true;
//$manager->cached_data=false;

$manager->plan();
print_r($manager->get_planning_info());

$manager->set_house(["consumption"=>30/24]);
$manager->plan();
print_r($manager->get_planning_info());
