<?php

/**
 * Simple Heatpump with quadratic equation for
 * electic power calculation
 */

namespace EnergyManager\Heatpump;
class HeatpumpQuadratic extends Heatpump{
    public function __construct($settings, \EnergyManager\Temp\Temp $temp_obj) {
        $this->defaults = [
            "heating_limit" => 15,
            "indoor_temp" => 20,
            "lin_coef" => null,
            "quad_coef" => null,
            "refresh" => 3600 * 3
        ];
        $this->setSettings($settings);
        $this->temp_obj = $temp_obj;
       
    }

    public function refresh(){
        return $this->temp_obj->refresh();
    }

    
    public function plan(\EnergyManager\PV\PV $pv_obj, \EnergyManager\Price\Price $price_obj):bool{
        if(! $this->refresh()) return false;
        $dt = new \DateTime();
        $dt->setTimestamp($this->time());
        $this->plan = [];
        $daily = $this->temp_obj->getDaily();
        foreach ($this->temp_obj->getHourly() as $hour => $value) {
            $dt->setTimestamp($hour);
            $temp = $daily[$dt->format('Y-m-d')] ?? -999;
            if ($temp == -999)
                break;
            $this->plan[$hour] = $this->getKw($temp);
        }
        return true;
    }

    private function getKw($temp){
        if ($temp > $this->settings['heating_limit']) 
          return 0;
        
        $t_diff = $this->settings['indoor_temp'] - $temp;
        return $this->settings['lin_coef'] * $t_diff +
                $this->settings['quad_coef'] * $t_diff * $t_diff; //Numbers from Auswertung_Heizung.R 

    }

}