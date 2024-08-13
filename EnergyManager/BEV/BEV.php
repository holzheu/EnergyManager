<?php
/**
 * Abstract BEV class
 */
namespace EnergyManager\BEV;

abstract class BEV extends \EnergyManager\Device
{
    protected $kwh = 20;
    protected $soc = 30;
    protected $min_kw = 2.2;
    protected $max_kw = 2.2;

    protected $min_soc = 55;
    protected $max_soc = 85;
    protected $charge_time = 2; //hours


    abstract public function charge($kw, $duration);

    public function getKWh()
    {
        return $this->kwh;
    }
    public function getSoc()
    {
        return $this->soc;
    }
    public function getMinKw()
    {
        return $this->min_kw;
    }
    public function getMaxKw()
    {
        return $this->max_kw;
    }
    public function getMinSoc()
    {
        return $this->min_soc;
    }
    public function getMaxSoc()
    {
        return $this->max_soc;
    }
    public function getChargeTime()
    {
        return $this->charge_time;
    }

    public function getStatus()
    {
        $out = "BEV Status\n";
        $out .= sprintf("kWh: %.1f\n", $this->kwh);
        $out .= sprintf("SOC: %.0f\n", $this->soc);
        $out .= sprintf("Min kW: %.1f\n", $this->min_kw);
        $out .= sprintf("Max kW: %.1f\n", $this->max_kw);
        $out .= sprintf("Min SOC: %.0f\n", $this->min_soc);
        $out .= sprintf("Max SOC: %.0f\n", $this->max_soc);
        $out .= sprintf("Time: %.2f\n", $this->charge_time);
        return $out;
    }

}

