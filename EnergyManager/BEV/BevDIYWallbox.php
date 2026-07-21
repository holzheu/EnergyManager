<?php 
/**
 * DIY Wallbox charger
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
class BevDIYWallbox extends BEV
{
    public $last_command;

    private int $phases = 1;

    public function __construct($settings)
    {
        $this->defaults = [
            "ip" => null,
            'kwh' => null,
            'min_kw' => null,
            'max_kw' => null,
            "refresh" => 30,
            'time_back'=>'15:30',
            'max_price_full_charge'=>50
        ];
        $this->setSettings($settings);

        
        $this->max_kw = $this->settings['max_kw'];
        $this->min_kw = $this->settings['min_kw'];
        $this->kwh = $this->settings['kwh'];
        $this->max_price_full_charge = $this->settings['max_price_full_charge'];

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
        $this->soc = $json["SOC"];
        $this->charge_time = $json["T"]; //hours
        $this->kwh = $json["KAP"];
        $this->min_soc = $json["Min"];
        $this->max_soc = $json["Max"];
        $phases = intval($json["P"]);
        if($this->phases != $phases) {
            if($phases == 3) {
                $this->min_kw*=3;
                $this->max_kw*=3;
            } else {
                $this->min_kw/=3;
                $this->max_kw/=3;
            }
            $this->phases = $phases;
        }
        return true;
    }

    public function charge($kw, $duration = 1 / 30)
    {
        $A = round($kw*1000/230/$this->phases);
        $json = file_get_contents(sprintf("http://%s/cmd?t=%.0f&A=%.0f", $this->settings['ip'], $duration * 60, $A));
        $charger = json_decode($json, true);
        if ($charger['A_actual'] != $A)
            fwrite(STDERR, date('Y-m-d H:i:s') . ' ' . "EnergyManager: Failed to swich on BEV charger\n");
        else
            $this->last_command = time();

    }
}