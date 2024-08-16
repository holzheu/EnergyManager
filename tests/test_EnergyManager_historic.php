#!/usr/bin/php
<?php

require_once __DIR__ . '/../EnergyManager/autoload.php';

$time = new \EnergyManager\Time();
//create objects
$bat = new \EnergyManager\Battery\BatteryDummy([
    'kwh' => 10,
    'soc' => 60,
    'charge_power' => 1.5,
    'md_min_soc'=>25
]);
$bat->setTimeObj($time);

$pv = new \EnergyManager\PV\PVFile();
$pv->setTimeObj($time);

$bev = new \EnergyManager\BEV\BevDummy([
    'kwh' => 30,
    'soc' => 90,
    'min_kw' => 1,
    'max_kw' => 4
]);
$bev->setTimeObj($time);

$house = new \EnergyManager\House\HouseConstant(['kwh_per_day' => 10]);
$house->setTimeObj($time);

$price = new \EnergyManager\Price\PriceFile();
$price->setTimeObj($time);

$temp = new \EnergyManager\Temp\TempFile();
$temp->setTimeObj($time);

$hp = new \EnergyManager\Heatpump\HeatpumpQuadratic([
    "lin_coef" => 0.017678,
    "quad_coef" => 0.002755
], $temp);



$manager = new \EnergyManager\EnergyManager($pv, $bat, $price, $house, $bev, $hp);
$manager->setTimeObj($time);

$dt = DateTime::createFromFormat("Y-m-d H", '2024-08-05 00');

$pv_kwh=1e-6;
$pv_e=0;
$feedin_kwh=1e-6;
$feedin_e=0;
$grid_kwh=1e-6;
$grid_e=0;
$hp_kwh=1e-6;;
$hp_e=0;
$bev_kwh=1e-6;;
$bev_e=0;
$house_kwh=1e-6;
$house_e=0;


$charge_hours =[
    17=>['soc'=> -15,'charge_time'=>14],
    24+17=>['soc' => -15, 'charge_time'=>13],
    24*2+17=>['soc' => -15, 'charge_time'=>13],
    24*3+17=>['soc' => -15, 'charge_time'=>13],
    24*4+17=>['soc' => -15, 'charge_time'=>14+24*2]
];
$charge_time=0;


for ($i = 0; $i < 24*7; $i++) {
    $bev->setChargeTime($charge_time);
    if($charge_time>=0) $charge_time--;
    $time->set($dt->getTimestamp());
    echo $manager->plan();
    $plan=$manager->get_planning_info($dt->getTimestamp());
    $euro=$plan['Preis'];
    $pv_kwh+=$plan['PV'];
    $pv_e+=$plan['PV']*$euro;
    if($plan['Netz']<0){
        $grid_kwh+= -$plan['Netz'];
        $grid_e+= -$plan['Netz']*$euro;
    } else {
        $feedin_kwh+=$plan['Netz'];
        $feedin_e+=$plan['Netz']*$euro;        
    }
    $house_kwh+=$plan['HOUSE'];
    $house_e+=$plan['HOUSE']*$euro;
    $hp_kwh+=$plan['HP'];
    $hp_e+=$plan['HP']*$euro;
    $bev_kwh+=$plan['BEV'];
    $bev_e+=$plan['BEV']*$euro;
    $bat->setSOC($plan['Bat']);
    $bev->add($plan['BEV']);
    echo "        PV     Bezug  Einsp.  BEV    HP    House\n";
    echo sprintf("kWh:   %6.1f %6.1f %6.1f %6.1f %6.1f %6.1f\n",
    $pv_kwh,$grid_kwh,$feedin_kwh,$bev_kwh,$hp_kwh,$house_kwh);
    echo sprintf("€/MWh: %6.1f %6.1f %6.1f %6.1f %6.1f %6.1f\n",
    $pv_e/$pv_kwh,$grid_e/$grid_kwh,$feedin_e/$feedin_kwh,$bev_e/$bev_kwh,$hp_e/$hp_kwh,$house_e/$house_kwh);
    $dt->modify("+1 hour");
    if(isset($charge_hours[$i])){
        $charge_time=$charge_hours[$i]['charge_time'];
        $bev->setChargeTime($charge_time);
        $bev->setSOC($bev->getSOC()+$charge_hours[$i]['soc']);
        echo sprintf("BEV plugged in with %.1f%% SOC and %.0f hours to charge\n",$bev->getSOC(),$charge_time);
        sleep(2);
    } else echo sprintf("BEV SOC: %5.1f%% -- %s\n",$bev->getSOC(),
    ($charge_time>0?sprintf("time to charge %.0f hours",$charge_time):'not present'));
    usleep(300000);

}



