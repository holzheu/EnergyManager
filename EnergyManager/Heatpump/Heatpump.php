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

    /**
     * get electricity demand of heat pump as
     * a function of temperature
     * @param mixed $temp temperatur
     * @return float electricity demand (kw)
     */
    abstract public function getKw($temp);
}

