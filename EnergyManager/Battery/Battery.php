<?php
/**
 * Battery Classes
 * 
 * 
 */

namespace EnergyManager\Battery;

/**
 * Abstract battery class
 */
abstract class Battery extends \EnergyManager\Device
{
    protected float $kwh; //capacity of battery
    protected float $soc; //current state of charge
    
    /**
     * Get current SOC (0-100)
     * @return float 
     */
    public function getSOC()
    {
        return $this->soc;
    }

    /**
     * Get capacity in kWh
     * @return float 
     */
    public function getCapacity()
    {
        return $this->kwh;
    }

    /**
     * kWh to SOC
     * @param mixed $kwh
     * @return float
     */
    public function kwh2soc(float $kwh)
    {
        return $kwh / $this->kwh * 100;
    }

    /**
     * SOC to kWh
     * @param mixed $soc
     * @return float
     */
    public function soc2kwh(float $soc)
    {
        return $soc / 100 * $this->kwh;
    }


    /**
     * Get string with current Battery status
     * @return string
     */
    public function getStatus(){
        $out= "Battery Status\n";
        $out.= sprintf("kWh: %.1f\n",$this->kwh);
        $out.= sprintf("SOC: %.0f\n",$this->soc);
        return $out;
    }

    /**
     * Set the mode of the battery
     * 'no charge': Only discharge but no charge
     * 'no discharge': Only charge but no discharge
     * 'active discharge': Discharge battery with at least $kw
     * 'active charge': Charge battery with $kw
     * @param string $mode
     * @param float $kw
     * @return void
     */
    abstract public function setMode(string $mode, float $kw = null);

}

