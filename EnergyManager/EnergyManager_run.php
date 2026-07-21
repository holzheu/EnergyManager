#!/usr/bin/php
<?php

require_once __DIR__ . '/EnergyManager.php';
require_once __DIR__ . '/secrets.php';


$price = new \EnergyManager\Price\PriceAwattar();
$temp = new \EnergyManager\Temp\TempOpenMeteo([
    "latitude" => OpenMeteo_latitude,
    "longitude" => OpenMeteo_longitude
]);
$hp = new \EnergyManager\Heatpump\HeatpumpDimplexDaikin([
    "lin_coef" => 0.017678,
    "quad_coef" => 0.002755,
    "daikin" => DaikinIPs,
    "daikin_timetable" => DaikinTimetable,
    'ip' => DimplexIP
], $temp);
$bev = new \EnergyManager\BEV\BevDIY([
    "ip" => BEV_DIV_ip,
    'kwh' => 17.9,
    'kw' => 2.2
]);

$wallbox = new \EnergyManager\BEV\BevDIYWallbox([
    "ip" => WALLBOX_ip,
    'kwh' => 24.3,
    'min_kw' => 230*6/1000,
    'max_kw' => 230*16/1000
]);

$ebike1 = new \EnergyManager\BEV\EBikeDIY([
    "ip" => EBike_ip,
    'kwh' => 0.8,
    'kw' => 0.2
]);

$ebike2 = new \EnergyManager\BEV\EBikeDIY([
    "ip" => EBike_ip,
    'kwh' => 0.4,
    'kw' => 0.08,
    'nr' => 1
]);

$bevs = new \EnergyManager\BEV\BEVArray();
$bevs->addBEV($ebike1);
$bevs->addBEV($ebike2);
$bevs->addBEV($bev);
$bevs->addBEV($wallbox);

$pv = new \EnergyManager\PV\PvForecastSolar([
    'lon' => ForecastSolar_lon,
    'lat' => ForecastSolar_lat,
    'dec' => [ForecastSolar_dec1, ForecastSolar_dec2],
    'az' => [ForecastSolar_az1, ForecastSolar_az2],
    'kwp' => [ForecastSolar_kwp1, ForecastSolar_kwp2]
]);

$house = new \EnergyManager\House\HouseConstant(['kwh_per_day' => 8]);
$bat = new \EnergyManager\Battery\BatteryKostalByd(['ip' => Kostal_Plenticore_Plus_ip]);


$manager = new \EnergyManager\EnergyManager($pv, $bat, $price, $house, $bevs, $hp);

$manager->setSettings([
    'charge_power' => Charge_Power
]);

require_once 'BayEOSGatewayClient.php';

//Configuration for BayEOS
$path = '/tmp/EnergyManager';
$name = "EnergyManager";
$url = "http://" . BayEOS_IP . "/gateway/frame/saveFlat";
$options = array('user' => BayEOS_USER);

//Create a BayEOSSimpleClient
//Note: This already forks the sender process
$c = new BayEOSSimpleClient($path, $name, $url, $options);

//Setup signal handling for SIGTERM
declare(ticks=1);
pcntl_signal(SIGTERM, function ($signo) {
    $GLOBALS['c']->stop();
});


$last_hour = '';
while (true) {
    $manager->run();
    $values = $manager->get_planning_info();
    $dt = new DateTime();
    if ($dt->format(DATE_H) != $last_hour) {
        $last_hour = $dt->format(DATE_H);
        fwrite(STDOUT, date('Y-m-d H:i:s') . ' ' . "EnergyManager: ");
        foreach ($values as $key => $value) {
            fwrite(STDOUT, $key . ": " . sprintf("%.2f", $value) . "; ");
        }
        fwrite(STDOUT, "\n");
        $dt->modify("+1 day");
        $day_ahead = $manager->get_planning_info($dt->getTimestamp());
        foreach ($day_ahead as $key => $value) {
            $values[$key . "_dayahead"] = $value;
        }

        $c->save($values, '', 0x61);
    }
    sleep(5);
}
