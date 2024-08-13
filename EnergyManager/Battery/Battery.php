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
    
    protected array $default_settings = [
        "ed_min_soc" => 50,
        "ed_soc_rate" => 20,
        "ed_min_price" => 60,
        "md_min_soc" => 15,
        "md_min_price" => 50,
        "md_soc_rate" => 15,
        "min_grid" => 5,
        "charge_power" => 0,
        "charge_max_price" => 50,
        "charge_min_price_diff" => 50
    ];

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

