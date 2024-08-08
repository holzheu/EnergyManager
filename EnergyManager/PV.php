<?php
/**
 * PV classes
 */

require_once(dirname(__FILE__) ."/Device.php");


/**
 * Abstract PV class
 */
abstract class PV extends Device {
    protected $production=[];

    public function getProduction(){
        return $this->production;
    }

}

/**
 * Dummy PV class
 * 
 * Uses an old json file from solarprognose for a dummy prediction for today and tomorrow
 */
class PV_Dummy extends PV {
    public function refresh(){
        $json_file = file_get_contents("solar.json");
        $json = json_decode($json_file, true);
        $pv = [];
        $dt= new DateTime();
        $today= DateTime::createFromFormat('Y-m-d H:i',$dt->format('Y-m-d 00:00'));
        $diff =0;
        foreach ($json['data'] as $time => $value) {
            $dt->setTimestamp($time);
            $dt->modify("-1 hour");
            if(! $diff) $diff = date_diff($dt, $today);
            $dt->modify($diff->format("%R%a days"));
            $dt->modify("+1 day");
            $pv[$dt->format(DATE_H)] = $value[0] ;

        }
        $this->production = $pv;
        return true;
    }
}

/**
 * Implementation for solarprognose.de
 * 
 * You need a configured plant (plant_id) and
 * an access token
 */
class PV_Solarprognose extends PV {

    public function __construct($settings) {
        $defaults = [
            "access_token" => null,
            "plant_id" => null,
            "factor" => 1,
            "refresh" => 3600 * 3
        ];
        $this->settings = $this->check_settings($settings, $defaults);

    }
    public function refresh(){
        $dt = new DateTime();
        if (($dt->getTimestamp() - $this->update) < $this->settings['refresh'])
            return true;
        fwrite(STDOUT, date('Y-m-d H:i:s') . ' ' . "EnergyManager: refresh solarprognose " . ($dt->getTimestamp() - $this->update) . "\n");
        $this->update = $dt->getTimestamp();

        $url = sprintf(
            'https://www.solarprognose.de/web/solarprediction/api/v1?access-token=%s&item=plant&id=%d&type=hourly&_format=json',
            $this->settings['access_token'],
            $this->settings['plant_id']
        );
        $cache_file = "/dev/shm/EnergyManager_solarprognose.json";
        clearstatcache();
        if(file_exists($cache_file) && ($dt->getTimestamp() - filemtime($cache_file)) < $this->settings["refresh"])
            $url=$cache_file;
        $json_file = file_get_contents($url);

        if (!$json_file) {
            fwrite(STDERR, date('Y-m-d H:i:s') . ' ' . "EnergyManager: failed to read solarprognose\n");
            return false;
        }
        $fp = fopen("/dev/shm/EnergyManager_solarprognose_tmp.json", "w");
        fwrite($fp, $json_file);
        fclose($fp);

        $json = json_decode($json_file, true);
        if ($json['status'] != 0) {
            fwrite(STDERR, date('Y-m-d H:i:s') . ' ' . "EnergyManager: failed to read solarprognose " . $json['message'] . "\n");
            if (!file_exists($cache_file))
                return false;
            fwrite(STDERR, date('Y-m-d H:i:s') . ' ' . "EnergyManager: continue with cached file\n");
            $json_file = file_get_contents($cache_file);
            $json = json_decode($json_file, true);

        }


        $pv = [];
        foreach ($json['data'] as $time => $value) {
            $dt->setTimestamp($time);
            $dt->modify("-1 hour");
            $pv[$dt->format(DATE_H)] = $value[0] * $this->settings['factor'];
        }

        $dt = new DateTime();
        $dt->modify("+1 day");
        if (!isset($pv[$dt->format('Y-m-d 12')])) {
            fwrite(STDERR, date('Y-m-d H:i:s') . ' ' . "EnergyManager: solarprognose invalid data\n");
            return false;
        }
        $this->production = $pv;

        if($url != $cache_file){
           $fp = fopen($cache_file, "w");
            fwrite($fp, $json_file);
            fclose($fp);
        }
        return true;
       

    }
}