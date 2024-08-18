#!/usr/bin/php
<?php

require_once __DIR__ . '/../EnergyManager/autoload.php';

$time = new \EnergyManager\Time();
//create objects

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


$kaps=range(1,80,3);

foreach ($kaps as $kap) {

    $dt = DateTime::createFromFormat("Y-m-d H", '2024-04-02 00');

$bat = new \EnergyManager\Battery\BatteryDummy([
    'kwh' => $kap,
    'soc' => 60,
    'charge_power' => 1.5,
    'md_min_soc' => 25
]);
$bat->setTimeObj($time);


$manager = new \EnergyManager\EnergyManager($pv, $bat, $price, $house, $bev, $hp);
$manager->setTimeObj($time);


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


$charge_hours = [
];
$charge_time = 7;

//$output='cli';
//$output='data';
$output='summary';

for ($i = 0; $i < 24 * 120; $i++) {
    $time->set($dt->getTimestamp());
    $bev->setChargeTime($charge_time);
    if ($charge_time >= 0)
        $charge_time--;
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
    if (isset($charge_hours[$i])) {
        $charge_time = $charge_hours[$i]['charge_time'];
        $bev->setChargeTime($charge_time);
        $bev->setSOC($bev->getSOC() + $charge_hours[$i]['soc']);
        $bev_text = sprintf("BEV plugged in with %.1f%% SOC and %.0f hours to charge\n", $bev->getSOC(), $charge_time);
    } else
        $bev_text = sprintf(
            "BEV SOC: %5.1f%% -- %s\n",
            $bev->getSOC(),
            ($charge_time > 0 ? sprintf("time to charge %.0f hours", $charge_time) : 'not present')
        );

    switch($output){
        case 'cli':
            echo $plan;
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
            echo $bev_text;
            break;
        case 'data':
            $head='Datatime';
            $line=$dt->format('Y-m-d H');
            foreach($data as $k => $v){
                $head.="\t".$k;
                $line.="\t".$v;
            }
            if($i==0) echo $head."\n";
            echo $line."\n";
            break;

    }
    $dt->modify("+1 hour");
    if($output=='cli') usleep(300000);

}
    if($output== 'summary') echo $kap."\t".$feedin_e / $feedin_kwh ."\n";

}

