<?php

require_once(dirname(__FILE__) ."/../EnergyManager/Heatpump.php");

$hp = new Heatpump_quadratic([
    "lin_coef" => 0.017678,
    "quad_coef" => 0.002755
]);

echo $hp->getKw(10)."\n";
echo $hp->getKw(17)."\n";

