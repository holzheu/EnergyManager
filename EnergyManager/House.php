<?php
/**
 * House classes
 */
require_once(dirname(__FILE__) ."/Device.php");

/**
 * Abstract House class
 */
abstract class House extends Device {

    /**
     * Get electricity demand of the house
     * without heatpump and bev
     * @param float $hour 
     * @return float electricity demand (kW)
     */
    abstract public function getKw($hour);
}


/**
 * Simple House class with constant
 */
class House_constant extends House {
    public function __construct($settings) {
        $defaults = [
            "kwh_per_day" => null
        ];
        $this->settings = $this->check_settings($settings, $defaults);
       
    }

    public function refresh(){
        return true;
    }

    public function getKw($hour){
        return $this->settings["kwh_per_day"]/24;
    }
}