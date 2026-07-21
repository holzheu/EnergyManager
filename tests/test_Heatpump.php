<?php

require_once __DIR__."/../EnergyManager/autoload.php";

$temp = new \EnergyManager\Temp\TempOpenMeteo([
    "latitude" => 50.8333,
    "longitude" => 11.8367
]);

$hp = new \EnergyManager\Heatpump\HeatpumpQuadratic([
    "lin_coef" => 0.017678,
    "quad_coef" => 0.002755
],$temp);

$pv = new \EnergyManager\PV\PvDummy();
$price = new \EnergyManager\Price\PriceAwattar();

$bat = new \EnergyManager\Battery\BatteryDummy([
    'kwh' => 10,
    'soc' => 80,
    'charge_power' => 1.5
]);

$house = new \EnergyManager\House\HouseConstant(['kwh_per_day' => 25]);


$em = new \EnergyManager\EnergyManager($pv, $bat, $price, $house, hp:$hp);

$pv->refresh();
$price->refresh();
$hp->plan($em);
print_r($hp->getPlan());
print_r($hp->getTemp());

