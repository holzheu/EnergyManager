<?php 
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

namespace EnergyManager\BEV;
class BevDIY extends BEV
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