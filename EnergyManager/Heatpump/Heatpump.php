<?php
/**
 * Heatpump classes
 * 
 */

namespace EnergyManager\Heatpump;

/**
 * Abstract Heatpump class
 */
abstract class Heatpump extends \EnergyManager\Device {

    protected array $plan=[];


    protected \EnergyManager\Temp\Temp $temp_obj;


    abstract public function __construct(array $settings, \EnergyManager\Temp\Temp $temp_obj);
    
    
    abstract public function plan(\EnergyManager\PV\PV $pv_obj, \EnergyManager\Price\Price $price_obj):bool;
 

    public function getPlan(){
        return $this->plan;
    }

    public function getTemp(){
        return $this->temp_obj->getHourly();
    }
}

