<?php
/**
 * BEV classes
 * 
 */

require_once (dirname(__FILE__) . "/Device.php");

/**
 * Abstract BEV class
 */
abstract class BEV extends Device
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


/**
 * Dummy BEV class
 */
class BEV_Dummy extends BEV
{

    public function __construct($settings = [])
    {
        $defaults = [
            'kwh' => 20,
            'soc' => 30,
            'time' => 2,
            'min_kw' => 2.2,
            'max_kw' => 2.2
        ];
        $this->settings = $this->check_settings($settings, $defaults);
        $this->max_kw = $this->settings['max_kw'];
        $this->min_kw = $this->settings['min_kw'];
        $this->kwh = $this->settings['kwh'];
        $this->soc = $this->settings['soc'];

    }


    public function refresh()
    {
        return true;
    }
    public function charge($kw, $duration)
    {

    }
}

/**
 * DIY charger
 * 
 * Small ESP-Device which gives the
 * - current SOC of the BEV
 * - Minimum SOC
 * - Maximum SOC
 * - Time left to charge
 * 
 * Switches on relais via get-Request 
 */
class BEV_DIY extends BEV
{
    public $last_command;

    public function __construct($settings)
    {
        $defaults = [
            "ip" => null,
            'kwh' => null,
            'kw' => null,
            "refresh" => 30
        ];
        $this->settings = $this->check_settings($settings, $defaults);
        $this->max_kw = $this->settings['kw'];
        $this->min_kw = $this->settings['kw'];
        $this->kwh = $this->settings['kwh'];

    }


    public function refresh()
    {
        if ((time() - $this->update) < $this->settings['refresh'])
            return true;

        $this->update = time();
        $json = file_get_contents("http://" . $this->settings['ip'] . "/status");
        if (!$json) {
            fwrite(STDERR, date('Y-m-d H:i:s') . ' ' . "EnergyManager: failed to read BEV\n");
            return false;
        }
        $json = json_decode($json, true);
        $this->soc = $json["soc"];
        $this->charge_time = $json["time"]; //hours
        $this->min_soc = $json["min"];
        $this->max_soc = $json["max"];
        return true;
    }

    public function charge($kw, $duration = 1 / 30)
    {
        $json = file_get_contents(sprintf("http://%s/cmd?t=%.0f", $this->settings['ip'], $duration * 60));
        $charger = json_decode($json, true);
        if ($charger['status'] != 3)
            fwrite(STDERR, date('Y-m-d H:i:s') . ' ' . "EnergyManager: Failed to swich on BEV charger\n");
        else
            $this->last_command = time();

    }
}