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
        
        $count=0;
        for($i=0;$i<count($json['time']);$i++){
            if($json['time'][$i]<$hour) continue;
            $pv[$json['time'][$i]] = $json['pv'][$i];
            $count++;
            if($count>30) break;
        }
        $this->production = $pv;
        return true;
    }
}