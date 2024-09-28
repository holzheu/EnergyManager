<?php

namespace EnergyManager\Temp;

/**
 * File Temp class
 * 
 * reads data from a json file
 */
class TempFile extends Temp {
    public function refresh(){
        $file=$this->settings["file"]??(__DIR__."/../../data/historic1.json");
        $json_file = file_get_contents($file);
        $json = json_decode($json_file, true);
        $this->hourly = [];
        $this->daily = [];
        $hour = $this->time();
        $dt = new \DateTime();
        $dt->setTimestamp($hour);
        $dt->modify("-1 day");
        $start= \DateTime::createFromFormat("Y-m-d H", $dt->format("Y-m-d 00"));
        $hour = $start->getTimestamp();
        $i=floor(($hour-$json['time'][0])/3600);
        if($i<0 || $i>=count($json['time'])){
            throw new \Exception('no data.');
        }
        $count=0;
        $mean=0;
        while($i<count($json['time'])){
            $dt->setTimestamp($json['time'][$i]);
            $this->hourly[$dt->getTimestamp()] = $json['temp'][$i];
            $mean+=$json['temp'][$i];
            $count++;
            if($count % 24 == 0){
                $dt->modify('-12 hours');
                $this->daily[$dt->format('Y-m-d')] = $mean / 24;
                $mean = 0;
            }           
            $i++;
            if($count>96) break;

        } 
        $this->calcRollMean();      
        return true;
    }
}