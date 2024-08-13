<?php

namespace EnergyManager\PV;

/**
 * Dummy PV class
 * 
 * Uses an old json file from solarprognose for a dummy prediction for today and tomorrow
 */
class PvDummy extends PV {
    public function refresh(){
        $json_file = file_get_contents(__DIR__."/../../tests/solar.json");
        $json = json_decode($json_file, true);
        $pv = [];
        $dt= new \DateTime();
        $today= \DateTime::createFromFormat('Y-m-d H:i',$dt->format('Y-m-d 00:00'));
        $diff =0;
        foreach ($json['data'] as $time => $value) {
            $dt->setTimestamp($time);
            $dt->modify("-1 hour");
            if(! $diff) $diff = date_diff($dt, $today);
            $dt->modify($diff->format("%R%a days"));
            $dt->modify("+1 day");
            $pv[$dt->getTimestamp()] = $value[0] ;

        }
        $this->production = $pv;
        return true;
    }
}