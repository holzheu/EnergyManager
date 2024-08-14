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

$pv->refresh();
$price->refresh();
$hp->plan($pv,$price);
print_r($hp->getPlan());
print_r($hp->getTemp());

