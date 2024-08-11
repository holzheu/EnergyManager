#!/usr/bin/php
<?php

require_once dirname(__FILE__) . '/EnergyManager.php';
require_once dirname(__FILE__) .'/secrets.php';

$price = new Price_Awattar();
$hp = new Heatpump_quadratic([
    "lin_coef" => 0.017678,
    "quad_coef" => 0.002755
]);
$temp = new Temp_OpenMeteo([
    "latitude" => OpenMeteo_latitude,
    "longitude" => OpenMeteo_longitude
]);
$bev = new BEV_DIY([
    "ip" => BEV_DIV_ip,
    'kwh' => 17.9,
    'kw' => 2.2
]);

$pv = new PV_Solarprognose([
    'access_token' => Solarprognose_access_token,
    'plant_id' => Solarprognose_plant_id,
    'factor' => 2
]);
$house = new House_constant(['kwh_per_day' => 10]);
$bat = new Battery_Kostal_Plenticore_Plus(['ip' => Kostal_Plenticore_Plus_ip]);


$manager = new EnergyManager($pv, $bat, $price, $house, $bev, $hp, $temp);



require_once 'BayEOSGatewayClient.php';

//Configuration for BayEOS
$path = '/tmp/EnergyManager';
$name = "EnergyManager";
$url = "http://".BayEOS_IP."/gateway/frame/saveFlat";
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
