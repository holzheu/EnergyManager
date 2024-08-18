#!/usr/bin/php
<?php

require_once __DIR__ . '/../EnergyManager/autoload.php';

$time = new \EnergyManager\Time();
//create objects
$bat = new \EnergyManager\Battery\BatteryDummy([
    'kwh' => 10,
    'soc' => 60,
    'charge_power' => 1.5
]);
$pv = new \EnergyManager\PV\PvDummy();
$bev = new \EnergyManager\BEV\BevDummy([
    'kwh' => 30,
    'soc' => 75,
    'min_kw' => 1,
    'max_kw' => 3
]);
$bev->setTimeObj($time);
$house = new \EnergyManager\House\HouseConstant(['kwh_per_day' => 5]);
$price = new \EnergyManager\Price\PriceAwattar();
$temp = new \EnergyManager\Temp\TempOpenMeteo([
    "latitude" => 50.8333,
    "longitude" => 11.8367
]);
$hp = new \EnergyManager\Heatpump\HeatpumpQuadratic([
    "lin_coef" => 0.017678,
    "quad_coef" => 0.002755
], $temp);



$manager = new \EnergyManager\EnergyManager($pv, $bat, $price, $house, $bev, $hp);
$manager->setTimeObj($time);

$dt = new DateTime();
$dt->setTimestamp(floor($dt->getTimestamp() / 3600) * 3600);
$dt->modify("-10 hours");
$pv_kwh = 1e-6;
$pv_e = 0;
$feedin_kwh = 1e-6;
$feedin_e = 0;
$grid_kwh = 1e-6;
$grid_e = 0;
$hp_kwh = 1e-6;
;
$hp_e = 0;
$bev_kwh = 1e-6;
;
$bev_e = 0;
$house_kwh = 1e-6;
$house_e = 0;



for ($i = 0; $i < 36; $i++) {
    $time->set($dt->getTimestamp());
    $plan= $manager->plan();
    $data = $manager->get_planning_info($dt->getTimestamp());
    $euro = $data['Preis'];
    $pv_kwh += $data['PV'];
    $pv_e += $data['PV'] * $euro;
    if ($data['Netz'] < 0) {
        $grid_kwh += -$data['Netz'];
        $grid_e += -$data['Netz'] * $euro;
    } else {
        $feedin_kwh += $data['Netz'];
        $feedin_e += $data['Netz'] * $euro;
    }
    $house_kwh += $data['HOUSE'];
    $house_e += $data['HOUSE'] * $euro;
    $hp_kwh += $data['HP'];
    $hp_e += $data['HP'] * $euro;
    $bev_kwh += $data['BEV'];
    $bev_e += $data['BEV'] * $euro;
    $bat->setSOC($data['Bat']);
    $bev->add($data['BEV']);
    echo $plan;
    echo sprintf("BEV SOC: %5.1f%%\n", $bev->getSOC());
    echo "        PV     Bezug  Einsp.  BEV    HP    House\n";
    echo sprintf(
        "kWh:   %6.1f %6.1f %6.1f %6.1f %6.1f %6.1f\n",
        $pv_kwh,
        $grid_kwh,
        $feedin_kwh,
        $bev_kwh,
        $hp_kwh,
        $house_kwh
    );
    echo sprintf(
        "€/MWh: %6.1f %6.1f %6.1f %6.1f %6.1f %6.1f\n",
        $pv_e / $pv_kwh,
        $grid_e / $grid_kwh,
        $feedin_e / $feedin_kwh,
        $bev_e / $bev_kwh,
        $hp_e / $hp_kwh,
        $house_e / $house_kwh
    );
    $dt->modify("+1 hour");
    sleep(1);

}



