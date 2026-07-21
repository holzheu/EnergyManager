<?php 
/**
 * DIY charger
 * 
 * Small ESP-Device which gives the
 * - current SOC of the EBike
 * - Minimum SOC
 * - Maximum SOC
 * - Time left to charge
 * 
 * Switches on relais via get-Request 
 */

namespace EnergyManager\BEV;
class EBikeDIY extends BEV
{
    public $last_command;

    private int $nr=0;

    public function __construct($settings)
    {
        $this->defaults = [
            "ip" => null,
            'kwh' => null,
            'kw' => null,
            "refresh" => 30,
            'time_back'=>'15:30',
            'max_price_full_charge'=>50
        ];
        $this->setSettings($settings);
        $this->max_kw = $this->settings['kw'];
        $this->min_kw = $this->settings['kw'];
        $this->kwh = $this->settings['kwh'];
        if(isset($this->settings['nr'])) $this->nr=$this->settings['nr'];
        $this->max_price_full_charge = $this->settings['max_price_full_charge'];

    }


    public function refresh()
    {
        if ((time() - $this->update) < $this->settings['refresh'])
            return true;

        $this->update = time();
        $json = file_get_contents("http://" . $this->settings['ip'] . "/status");
        if (!$json) {
            fwrite(STDERR, date('Y-m-d H:i:s') . ' ' . "EnergyManager: failed to read EBike\n");
            return false;
        }
        $json = json_decode($json, true);
        $this->soc = $json["soc".$this->nr];
        $this->charge_time = $json["time".$this->nr]; //hours
        $this->min_soc = $json["min".$this->nr];
        $this->max_soc = $json["max".$this->nr];
        if($this->min_soc>70) $this->min_soc=70;
        if($this->max_soc>70) $this->max_soc=70;
        return true;
    }

    public function charge($kw, $duration = 1 / 30)
    {
        $json = file_get_contents(sprintf("http://%s/cmd?p=%d&t=%.0f", $this->settings['ip'], $this->nr, $duration * 60));
        $charger = json_decode($json, true);
        if ($charger['status'] != 1)
            fwrite(STDERR, date('Y-m-d H:i:s') . ' ' . "EnergyManager: Failed to swich on EBike charger\n");
        else
            $this->last_command = time();

    }
}