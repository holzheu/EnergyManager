<?php

require_once __DIR__."/../EnergyManager/EnergyManager.php";

$price = new \EnergyManager\Price\PriceAwattar();

$price->refresh();
print_r($price->getPrice());

$now=time();
print_r($price->get_ordered_price_slice($now, $now+10*3600));
print_r($price->get_ordered_price_slice($now, $now+10*3600,true));

