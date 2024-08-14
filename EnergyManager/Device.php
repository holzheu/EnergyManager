<?php

namespace EnergyManager;

define("DATE_H", "Y-m-d H");

abstract class Device
{
    protected array $settings = []; //settings array
    protected array $defaults = []; //default settings
    protected float $update = 0; //last update timestamp

    protected ?\EnergyManager\Time $time_obj=null; //Time object (usefull for debugging)

     /**
     * Returns the time 
     * normally this is just time()
     * But for debugging it is helpful to calculate the plan for
     * timestamps different from time()
     * @return float|int
     */
    public function time()
    {
        if ($this->time_obj===null) return time();
        return $this->time_obj->get();
    }

 
    /**
     * Returns the timestamp of the full hour
     * @param float $time
     * @return int
     */
    protected static function full_hour(float $time): int
    {
        return (int) floor($time / 3600) * 3600;
    }


    /**
     * Gives a factor of what is left from the hour (0-1)
     * @return float
     */
    protected function hour_left(float $hour)
    {
        $hour = $this->full_hour($hour);
        $now = $this->time();
        $factor = (3600 - ($now - $hour)) / 3600;
        if ($factor < 0)
            $factor = 0;
        if ($factor > 1)
            $factor = 1;
        return $factor;

    }
   

    /**
     * Function to check a check a settings array against a default array. 
     * When one key stays NULL, the skript will exit
     * @param array $settings
     * @throws \Exception
     * @return void
     */
    public function setSettings(array $settings)
    {
        foreach ($this->defaults as $key => $value) {
            if(! isset($settings[$key]) && isset($this->settings[$key])) continue;
            if (!isset($settings[$key]))
                $settings[$key] = $value;
            if (is_null($settings[$key])) {
                throw new \Exception(get_called_class() . "[" . $key . "] must not be null");
            }
        }
        $this->settings=array_merge($this->settings,$settings);
    }


    /**
     * Set the time object
     * @param \EnergyManager\Time $time_obj
     * @return void
     */
    public function setTimeObj(\EnergyManager\Time $time_obj){
        $this->time_obj = $time_obj;
    }

    /**
     * get the Setting array
     * @return array
     */
    public function getSettings()
    {
        return $this->settings;
    }

    /**
     * Update method to be implemented
     * @return bool
     */
    abstract public function refresh();


}