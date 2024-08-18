<?php

namespace EnergyManager\PV;

/**
 * File PV class
 * 
 * reads data from a json file
 */
class PVFile extends PV {
    public function refresh(){
        $file=$this->settings["file"]??(__DIR__."/../../data/historic1.json");
        $json_file = file_get_contents($file);
        $json = json_decode($json_file, true);
        $pv = [];
        $hour = $this->time();
        $hour = $this->full_hour($hour);
        $i=floor(($hour-$json['time'][0])/3600);
        if($i<0 || $i>=count($json['time'])){
            throw new \Exception('no data.');
        }
        $count=0;
        while($i<count($json['time'])){
            $pv[$json['time'][$i]] = $json['pv'][$i];
            $count++;
            if($count>30) break;
            $i++;

        }        
        $this->production = $pv;
        return true;
    }
}