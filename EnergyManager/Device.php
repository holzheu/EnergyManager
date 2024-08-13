<?php

namespace EnergyManager;

define("DATE_H", "Y-m-d H");

abstract class Device
{
    protected array $settings = []; //settings array
    protected float $update = 0; //last update timestamp

    /**
     * Function to check a check a settings array against a default array. 
     * When one key stays NULL, the skript will exit
     * @param array $settings
     * @param array $defaults
     * @return array settings array filled with default values
     */
    public static function check_settings(array $settings, array $defaults)
    {
        foreach ($defaults as $key => $value) {
            if (!isset($settings[$key]))
                $settings[$key] = $value;
            if (is_null($settings[$key])) {
                throw new Exception(get_called_class() . "[" . $key . "] must not be null");
            }
        }
        return $settings;
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