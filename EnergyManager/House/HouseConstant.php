<?php
/**
 * Abstract House class
 */

namespace EnergyManager\House;

/**
 * Simple House class with constant
 */
class HouseConstant extends House {
    public function __construct($settings) {
        $this->defaults = [
            "kwh_per_day" => null
        ];
        $this->setSettings($settings);
       
    }

    public function refresh(){
        return true;
    }

    public function getKw($hour){
        return $this->settings["kwh_per_day"]/24;
    }
}