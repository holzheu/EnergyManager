<?php

require_once (dirname(__FILE__) . "/../EnergyManager/Price.php");

$price = new Price_Awattar();

$price->refresh();
print_r($price->getPrice());

$now=time();
print_r($price->get_ordered_price_slice($now, $now+10*3600));
print_r($price->get_ordered_price_slice($now, $now+10*3600,true));

