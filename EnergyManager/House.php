<?php

require_once(dirname(__FILE__) ."/Device.php");

abstract class House extends Device {
    abstract public function getKw($hour);
}


class House_constant extends House {
    public function __construct($settings) {
        $defaults = [
            "kwh_per_day" => null
        ];
        $this->settings = $this->check_settings($settings, $defaults, "api.open-meteo.com");
       
    }

    public function refresh(){
        return true;
    }

    public function getKw($hour){
        return $this->settings["kwh_per_day"]/24;
    }
}