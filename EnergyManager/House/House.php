<?php
/**
 * Abstract House class
 */

namespace EnergyManager\House;
abstract class House extends \EnergyManager\Device {

    protected $plan = [];

    abstract public function plan(\EnergyManager\EnergyManager $em);

    public function getPlan(){
        return $this->plan;
    }

}

