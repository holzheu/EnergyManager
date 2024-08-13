<?php
/**
 * Abstract PV class
 */
namespace EnergyManager\PV;
abstract class PV extends \EnergyManager\Device {
    protected $production=[];

    public function getProduction(){
        return $this->production;
    }

}


