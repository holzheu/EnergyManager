<?php
/**
 * Dummy Battery Class for testing
 */
namespace EnergyManager\Battery;

class BatteryDummy extends Battery
{
    /**
     * Constructor
     * @param array $settings
     */
    public function __construct(array $settings)
    {
        $defaults = array_merge($this->default_settings, 
        [
            "kwh" => null,
            "soc" => null
        ]);
        $this->settings = $this->check_settings($settings, $defaults);
        $this->kwh = $this->settings['kwh'];
        $this->soc = $this->settings['soc'];
    }

    /**
     * Implementation of refresh method
     * @return bool
     */
    public function refresh(){
        return true;
    }

    /**
     * Implementation of setMode method
     * @param mixed $mode
     * @param mixed $kw
     * @return void
     */
    public function setMode($mode, $kw = null)
    {

    }

}

