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

    public function plan(\EnergyManager\EnergyManager $em){
        $this->plan=[];
        $free_prod = $em->getFreeProduction();
        foreach($free_prod as $hour => $prod){
            $this->plan[$hour]= $this->settings["kwh_per_day"]/24;
        }
        $em->updateFreeProduction($this->plan);
    }


    public function refresh(){
        return true;
    }

}