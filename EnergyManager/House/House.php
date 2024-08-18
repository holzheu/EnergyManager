<?php
/**
 * Abstract House class
 */

namespace EnergyManager\House;
abstract class House extends \EnergyManager\Device {

    protected $plan = [];

    abstract public function plan(array $free_prod, \EnergyManager\Price\Price $price_obj);

    public function getPlan(){
        return $this->plan;
    }

}

