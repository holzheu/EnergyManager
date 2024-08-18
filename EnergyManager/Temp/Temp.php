<?php
/**
 * Abstract Temp class
 */

namespace EnergyManager\Temp;
abstract class Temp extends \EnergyManager\Device {

    protected array $hourly = [];
    protected array $daily = [];

    /**
     * get the temperature forecast in hourly resolution
     * @return array
     */
    public function getHourly(){
        return $this->hourly;
    }

    /**
     * get temperature forecast as daily means
     * @return array
     */
    public function getDaily(){
        return $this->daily;
    }
}
