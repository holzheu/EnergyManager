<?php 
/**
 * Implementation for OpenMeto
 */

namespace EnergyManager\Temp;
class TempOpenMeteo extends Temp {
    public function __construct(array $settings = []) {
        $this->defaults = [
            "longitude" => null,
            "latitude" => null,
            "refresh" => 3600 * 3
        ];
        $this->setSettings($settings);
       
    }

    public function refresh(){
        $dt = new \DateTime();
        if (($dt->getTimestamp() - $this->update) < $this->settings['refresh'])
            return true;
        $this->update = $dt->getTimestamp();
        fwrite(STDOUT, date('Y-m-d H:i:s') . ' ' . "EnergyManager: refresh Temperatures\n");
        $url = sprintf(
            "https://api.open-meteo.com/v1/forecast?latitude=%f&longitude=%f&hourly=temperature_2m&models=icon_seamless",
            $this->settings['latitude'],
            $this->settings['longitude']
        );
        $json = file_get_contents($url);
        if (!$json) {
            fwrite(STDERR, date('Y-m-d H:i:s') . ' ' . "EnergyManager: failed to read Temperatures\n");
            return false;
        }
        $json = json_decode($json, true);
        $this->hourly = [];
        $this->daily = [];
        $mean = 0;
        for ($i = 0; $i < count($json['hourly']['time']); $i++) {
            $dt = \DateTime::createFromFormat('Y-m-d\\TH:i', $json['hourly']['time'][$i], new \DateTimeZone('GMT'));
            $dt->setTimezone(new \DateTimeZone('Europe/Berlin'));
            $this->hourly[$dt->getTimestamp()] = $json['hourly']['temperature_2m'][$i];
            $mean += $this->hourly[$dt->getTimestamp()];
            if ($i % 24 == 23) {
                $dt->modify('-12 hours');
                $this->daily[$dt->format('Y-m-d')] = $mean / 24;
                $mean = 0;
            }
        }
        return true;


    }

}