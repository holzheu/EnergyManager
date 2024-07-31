#!/usr/bin/php
<?php

require_once dirname(__FILE__) . '../EnergyManager/EnergyManager.php';

$manager = new EnergyManager();
$manager->set_pv([
    'access_token' => 'secret',
    'plant_id' => 53973,
    'factor' => 2
]);
$manager->set_battery(['ip' => '192.168.2.100']);
$manager->set_temp([
    "latitude" => 51.0451,
    "longitude" => 12.5477
]);
$manager->set_heatpump([
    "lin_coef" => 0.017678,
    "quad_coef" => 0.002755
]);
$manager->set_house(["consumption"=>10/24]);
$manager->set_bev(["ip"=>"192.168.2.101"]);



require_once 'BayEOSGatewayClient.php';

//Configuration
$path = '/tmp/EnergyManager';
$name = "EnergyManager";
$url = "http://192.168.2.108/gateway/frame/saveFlat";
$options = array('user' => 'import');

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
        $day_ahead = $manager->get_planning_info($dt->format(DATE_H));
        foreach ($day_ahead as $key => $value) {
            $values[$key . "_dayahead"] = $value;
        }

        $c->save($values, '', 0x61);
    }
    sleep(5);
}
