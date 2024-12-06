<?php

namespace EnergyManager\PV;

class PvForecastSolar extends PV
{
    public function __construct($settings)
    {
        $this->defaults = [
            "lon" => null,
            "lat" => null,
            "dec" => null,
            "az" => null,
            "kwp" => null,
            "refresh" => 3600 * 3
        ];
        if (!is_array($settings['dec']))
            $settings['dec'] = [$settings['dec']];
        if (!is_array($settings['az']))
            $settings['az'] = [$settings['az']];
        if (!is_array($settings['kwp']))
            $settings['kwp'] = [$settings['kwp']];

        $this->setSettings($settings);

    }

    public function refresh()
    {
        $dt = new \DateTime();
        if (($dt->getTimestamp() - $this->update) < $this->settings['refresh'])
            return true;
        fwrite(STDOUT, date('Y-m-d H:i:s') . ' ' . "EnergyManager: refresh ForecastSolar\n");
        $this->update = $dt->getTimestamp();

        $production=[];
        for ($i = 0; $i < count($this->settings['dec']); $i++) {
            $url = sprintf(
                'https://api.forecast.solar/estimate/%s/%s/%s/%s/%s',
                $this->settings['lat'],
                $this->settings['lon'],
                $this->settings['dec'][$i],
                $this->settings['az'][$i],
                $this->settings['kwp'][$i]
            );
            $cache_file = "/dev/shm/EnergyManager_ForecastSolar$i.json";
            clearstatcache();
            if (file_exists($cache_file) && ($dt->getTimestamp() - filemtime($cache_file)) < $this->settings["refresh"])
                $url = $cache_file;
            $json_file = file_get_contents(
                $url,
                false,
                stream_context_create([
                    'http' => [
                        'method' => "GET",
                        // Use newline \n to separate multiple headers
                        'header' => "Accept: application/json",
                    ]
                ])
            );

            if (!$json_file) {
                fwrite(STDERR, date('Y-m-d H:i:s') . ' ' . "EnergyManager: failed to read ForecastSolar\n");
                return false;
            }
            $json = json_decode($json_file, true);

            if($json['message']['code']){
                fwrite(STDERR, date('Y-m-d H:i:s') . ' ' . "EnergyManager: failed to read ForecastSolar " . $json['message']['text'] . "\n");
                return false;
            }
            foreach ($json['result']['watts'] as $time => $value) {
                $dt2 = new \DateTime($time, new \DateTimeZone($json['message']['info']['timezone']));
                $hour = $dt2->getTimestamp();
                $hour = floor($hour/3600)*3600;
                $production[$hour]=($production[$hour]??0)+$value/1000;
            }
            if ($url != $cache_file) {
                $fp = fopen($cache_file, "w");
                fwrite($fp, $json_file);
                fclose($fp);
            }

        }
        $this->production=$production;

        return true;


    }

}