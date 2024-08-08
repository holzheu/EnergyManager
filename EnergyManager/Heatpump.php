<?php

require_once(dirname(__FILE__) ."/Device.php");

abstract class Heatpump extends Device {
    abstract public function getKw($temp);
}


class Heatpump_quadratic extends Heatpump{
    public function __construct($settings) {
        $defaults = [
            "heating_limit" => 15,
            "indoor_temp" => 20,
            "lin_coef" => null,
            "quad_coef" => null,
            "refresh" => 3600 * 3
        ];
        $this->settings = $this->check_settings($settings, $defaults, "api.open-meteo.com");
       
    }

    public function refresh(){
        return true;
    }

    public function getKw($temp){
        if ($temp > $this->settings['heating_limit']) 
          return 0;
        
        $t_diff = $this->settings['indoor_temp'] - $temp;
        return $this->settings['lin_coef'] * $t_diff +
                $this->settings['quad_coef'] * $t_diff * $t_diff; //Numbers from Auswertung_Heizung.R 

    }

}