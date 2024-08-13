<?php

require_once __DIR__."/../EnergyManager/EnergyManager.php";

$hp = new \EnergyManager\Heatpump\HeatpumpQuadratic([
    "lin_coef" => 0.017678,
    "quad_coef" => 0.002755
]);

echo $hp->getKw(10) . "\n";
echo $hp->getKw(17) . "\n";

