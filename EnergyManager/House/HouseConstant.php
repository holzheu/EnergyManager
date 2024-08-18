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

    public function plan(array $free_prod, \EnergyManager\Price\Price $price_obj){
        $this->plan=[];
        foreach($free_prod as $hour => $prod){
            $this->plan[$hour]= $this->settings["kwh_per_day"]/24;
        }
    }


    public function refresh(){
        return true;
    }

}