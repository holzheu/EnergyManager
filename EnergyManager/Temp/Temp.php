<?php
/**
 * Abstract Temp class
 */

namespace EnergyManager\Temp;
abstract class Temp extends \EnergyManager\Device {

    protected array $hourly = [];
    protected array $roll_mean=[];
    protected array $daily = [];

    /**
     * get the temperature forecast in hourly resolution
     * @return array
     */
    public function getHourly(){
        return $this->hourly;
    }
    public function getRollMean(){
        return $this->roll_mean;
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

    protected function calcRollMean(){
        $count=0;
        $stack=[];
        $sum=0;
        $this->roll_mean=[];        
        $dt=new \DateTime();
        foreach($this->hourly as $hour=>$temp){
            array_push($stack, $temp);
            $count++;
            $sum+=$temp;
            if($count>=24){
                $dt->setTimestamp($hour);
                $dt->modify('-23 hours');
                $this->roll_mean[$dt->getTimestamp()]=$sum/24;
                $sum-=array_shift($stack);
            }
        }
    }
}
