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

    public function getMean(int $hours=24): float
    {
        $hour = $this->full_hour($this->time());
        $mean = 0;
        $count = 0;
        while (isset($this->hourly[$hour])) {
            $mean += $this->hourly[$hour];
            $hour += 3600;
            $count++;
            if ($count == $hours)
                break;
        }
        if (!$count)
            return NAN;
        return $mean / $count;

    }
}
