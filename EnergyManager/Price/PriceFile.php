<?php

namespace EnergyManager\Price;

/**
 * File Price class
 * 
 * reads data from a json file
 */
class PriceFile extends Price {
    public function refresh(){
        $file=$this->settings["file"]??(__DIR__."/../../data/historic1.json");
        $json_file = file_get_contents($file);
        $json = json_decode($json_file, true);
        $this->price = [];
        $hour = $this->time();
        $hour = $this->full_hour($hour);
        $dt = new \DateTime();
        $dt->setTimestamp($hour);
        if($dt->format("H")>'13'){
            $dt->modify('+1 day');
        }
        $end_time=$dt->format('Y-m-d 23');
        $i=floor(($hour-$json['time'][0])/3600);
        if($i<0 || $i>=count($json['time'])){
            throw new \Exception('no data.');
        }
        while($i<count($json['time'])){
            $dt->setTimestamp($json['time'][$i]);
            if($dt->format('Y-m-d H')> $end_time) break;       
            $this->price[$json['time'][$i]] = $json['price'][$i];
            $i++;

        }
        return true;
    }
}