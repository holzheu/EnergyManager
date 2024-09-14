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
    protected array $mode=[];

    protected \EnergyManager\Temp\Temp $temp_obj;


    abstract public function __construct(array $settings, \EnergyManager\Temp\Temp $temp_obj);
    
    
    abstract public function plan(array $free_prod, \EnergyManager\Price\Price $price_obj):bool;

    abstract public function setMode(string $mode);
 

    public function getPlan(){
        return $this->plan;
    }

    public function getMode(){
        return $this->mode;
    }

    public function getTemp(){
        return $this->temp_obj->getHourly();
    }
}

