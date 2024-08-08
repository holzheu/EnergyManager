<?php

require_once (dirname(__FILE__) . "/../EnergyManager/Price.php");

$price = new Price_Awattar();

$price->refresh();
echo $price->getStatus();

$dt = new DateTime();
print_r($price->get_ordered_price_slice($dt->format("Y-m-d 10"), $dt->format("Y-m-d 20")));

