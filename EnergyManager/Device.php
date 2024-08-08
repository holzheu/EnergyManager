<?php
define("DATE_H", "Y-m-d H");

abstract class Device{
    protected Array $settings=[];
    protected float $update =0;

     /**
     * Function to check a check a settings array against a default array. 
     * When one key stays NULL, the skript will exit
     * @param array $settings
     * @param array $defaults
     * @param string $funcname
     * @return array settings array filled with default values
     */
    public static function check_settings(array $settings, array $defaults, string $funcname)
    {
        foreach ($defaults as $key => $value) {
            if (!isset($settings[$key]))
                $settings[$key] = $value;
            if (is_null($settings[$key])) {
                throw new Exception($funcname . "[" . $key . "] must not be null");
            }
        }
        return $settings;
    }

    public function getSettings(){
        return $this->settings;
    }

    abstract public function refresh();

}