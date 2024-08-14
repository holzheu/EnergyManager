<?php

namespace EnergyManager\BEV;

/**
 * Dummy BEV class
 */
class BevDummy extends BEV
{

    public function __construct($settings = [])
    {
        $this->defaults = [
            'kwh' => 20,
            'soc' => 30,
            'time' => 2,
            'min_kw' => 2.2,
            'max_kw' => 2.2
        ];
        $this->setSettings($settings);
        $this->max_kw = $this->settings['max_kw'];
        $this->min_kw = $this->settings['min_kw'];
        $this->kwh = $this->settings['kwh'];
        $this->soc = $this->settings['soc'];

    }

    public function add($kwh){
        $this->soc+=$kwh/$this->kwh*100;
        if($this->soc>100) $this->soc= 100;
    }

    public function refresh()
    {
        return true;
    }
    public function charge($kw, $duration)
    {

    }
}
