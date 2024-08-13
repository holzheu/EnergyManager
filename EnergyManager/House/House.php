<?php
/**
 * Abstract House class
 */

namespace EnergyManager\House;
abstract class House extends \EnergyManager\Device {

    /**
     * Get electricity demand of the house
     * without heatpump and bev
     * @param float $hour 
     * @return float electricity demand (kW)
     */
    abstract public function getKw($hour);
}

