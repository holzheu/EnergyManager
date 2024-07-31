<?php
/**
 * Battery Manager for Kostal Plenticore Plus
 * 
 * uses api from solarprognose.de to get an estimate of the pv production
 * uses api from awattar.de to get price information
 * uses api from api.open-meteo.com to get temperature data
 * 
 * BEV is a DIY-device 
 * 
 * The battery of the inverter is controlled via modbustcp
 * 
 * @author Stefan Holzheu <stefan.holzheu@uni-bayreuth.de>
 * 
 * Status information is written to files in /dev/shm/
 * 
 */
require_once 'PHPModbus/ModbusMasterTcp.php';


define("DATE_H", "Y-m-d H");
define("BAT_OK", 0x1);
define("PV_OK", 0x2);
define("PRICE_OK", 0x4);
define("TEMP_OK", 0x8);
define("HEATPUMP_OK", 0x10);
define("HOUSE_OK", 0x20);
class EnergyManager
{
    public $verbous = false;
    public $cached_data = false;
    private $planing_status = BAT_OK | PV_OK | PRICE_OK | TEMP_OK | HEATPUMP_OK;    
    private $settings_status = BAT_OK | PV_OK | TEMP_OK | HEATPUMP_OK | HOUSE_OK;
    private $pv = []; //Array with estimated PV-production in kWh per hour
    private $pv_settings = []; //Array with pv settings
    private $pv_update = 0;
    private $price = []; //Array with prices in €/MWh per hour
    private $price_update = 0;
    private $price_min_today = 3000;
    private $price_min_tomorrow = 3000;
    private $bev = [];  //Array with estimated BEV consumption in kWh per hour
    private $bev_settings = [];
    private $bev_status;//Status return from ESP DIY Charger
    private $bev_update = 0;
    private $bev_last_command = 0;
    private $battery_kwh;
    private $battery_status = [];
    private $battery = []; //Array with SOC hours
    private $battery_flow = []; //Array with battery flow in kWh (+ charge battery, - discharge battery)
    private $battery_restrictions = []; //Array with restrictions
    private $battery_settings = []; //Array with battery settings
    private $battery_last_write;
    private $battery_error_count;

    private $temp = []; //Array with temperatures
    private $temp_settings = []; //array with settings for temperature forecast
    private $temp_means = [];//Array with mean daily temperatures
    private $temp_update = 0;

    private $grid = []; //Array with estimated grid-flow in kWh per hour (+ grid feed in, - grid power supply)
    private $modbus; //phpmodbus Object

    private $house = []; //Array with estimated house consumption in kWh per hour
    private $house_settings = [];

    private $heatpump = []; //Array with estimated heat pump consumption in kWh per hour
    private $heatpump_settings = []; //Array with heatpump settings
    private $heatpump_update = 0;





    /**
     * EnergyManager
     *
     * constructor 
     */
    public function __construct()
    {
    }

    /**
     * Function to check a check a settings array against a default array. 
     * When one key stays NULL, the skript will exit
     * @param mixed $settings
     * @param mixed $defaults
     * @param mixed $funcname
     * @return mixed settings array filled with default values
     */
    private static function check_settings($settings, $defaults, $funcname)
    {
        foreach ($defaults as $key => $value) {
            if (!isset($settings[$key]))
                $settings[$key] = $value;
            if (is_null($settings[$key])) {
                fwrite(STDERR, date('Y-m-d H:i:s') . ' ' . "EnergyManager: " . $funcname . "[" . $key . "] must not be null!\n");
                exit();
            }
        }
        return $settings;
    }

    /**
     * Set settings for house 
     *     
     * @param array $settings Array of settings
     * @return void
     */
    public function set_house($settings)
    {
        $defaults = [
            "consumption" => null
        ];
        $this->house_settings = $this->check_settings($settings, $defaults, "house");
        $this->settings_status &= ~HOUSE_OK;


    }

    /**
     * Set settings for bev 
     *     
     * @param array $settings Array of settings
     * @return void
     */
    public function set_bev($settings)
    {
        $defaults = [
            "ip" => null,
            "refresh" => 30
        ];
        $this->bev_settings = $this->check_settings($settings, $defaults, "BEV");
        $this->settings_status &= ~BEV_OK;

    }


    /**
     * Set settings for solarprognose.de. 
     * You have to give at least access_token and plant_id
     *     
     * @param array $settings Array of settings für solarprognose.de
     * @return void
     */
    public function set_pv($settings)
    {
        $defaults = [
            "access_token" => null,
            "plant_id" => null,
            "factor" => 1,
            "refresh" => 3600 * 3
        ];
        $this->pv_settings = $this->check_settings($settings, $defaults, "solarprognose.de");
        $this->settings_status &= ~PV_OK;


    }

    /**
     * Set settings for temperature forecast. 
     * You have to give longitude and latitude
     *     
     * @param array $settings Array of settings 
     * @return void
     */
    public function set_temp($settings)
    {
        $defaults = [
            "longitude" => null,
            "latitude" => null,
            "refresh" => 3600 * 3
        ];
        $this->temp_settings = $this->check_settings($settings, $defaults, "api.open-meteo.com");
        $this->settings_status &= ~TEMP_OK;
    }

    /**
     * Set settings for heat pump. 
     * You have to give 
     *     
     * @param array $settings Array of settings 
     * @return void
     */
    public function set_heatpump($settings)
    {
        $defaults = [
            "heating_limit" => 15,
            "indoor_temp" => 20,
            "lin_coef" => null,
            "quad_coef" => null,
            "refresh" => 3600 * 3
        ];
        $this->heatpump_settings = $this->check_settings($settings, $defaults, "heatpump");
        $this->settings_status &= ~HEATPUMP_OK;

    }

    /**
     * Set settings for battery
     * You have to give at least the ip of the battery
     *  
     * @param array $settings Array of battery settings
     * @return void
     */
    public function set_battery($settings)
    {
        $defaults = [
            "ip" => null,
            "plant_id" => 71,
            "ed_min_soc_morning" => 40,
            "ed_soc_rate" => 20,
            "ed_min_price" => 60,
            "md_min_soc" => 15,
            "md_min_price" => 50,
            "md_min_grid" => 5,
            "md_soc_rate" => 15
        ];
        $this->battery_settings = $this->check_settings($settings, $defaults, "BatterySettings");
        $this->modbus = new ModbusMasterTcp($this->battery_settings['ip'], "1502");
        $this->battery_kwh = 0;
        while (!$this->battery_kwh) {
            try {
                // FC 3
                $recData = $this->modbus->readMultipleRegisters($this->battery_settings['plant_id'], 1068, 2);
                $this->battery_kwh = PhpType::bytes2float($recData) / 1000;

            } catch (Exception $e) {
                // Print error information if any
                fwrite(STDERR, date('Y-m-d H:i:s') . ' ' . "EnergyManager: failed to read battery capacity: $e\n");
                sleep(10);
            }

        }
        fwrite(STDOUT, date('Y-m-d H:i:s') . ' ' . "EnergyManager: Successfully set battery with ip $settings[ip] and " . $this->battery_kwh . " kWh\n");

    }

    /**
     * Returns a ordered price slice of the price Array
     * used for planing...
     * 
     * @param mixed $start Start hour in the format "Y-m-d H"
     * @param mixed $end End hour (not included)
     * @param mixed $desc Order - set true for descend order
     * @return array 
     */
    private function get_ordered_price_slice($start, $end = null, $desc = false)
    {
        $hours = array_keys($this->price);
        $start = array_search($start, $hours);
        if ($start === false)
            return [];
        if (!is_null($end)) {
            $end = array_search($end, $hours);
            $end = $end ? $end - $start : null; //length
        }
        $price = array_slice($this->price, $start, $end, true);
        if ($desc)
            arsort($price);
        else
            asort($price);
        return $price;
    }



    /**
     * Refreshes the temperature forecast
     * 
     * @return void
     */
    private function refresh_temp()
    {
        $dt = new DateTime();
        if (($dt->getTimestamp() - $this->temp_update) < $this->temp_settings['refresh'])
            return;
        $this->temp_update = $dt->getTimestamp();
        fwrite(STDOUT, date('Y-m-d H:i:s') . ' ' . "EnergyManager: refresh Temperatures\n");
        $url = sprintf(
            "https://api.open-meteo.com/v1/forecast?latitude=%f&longitude=%f&hourly=temperature_2m&models=icon_seamless",
            $this->temp_settings['latitude'],
            $this->temp_settings['longitude']
        );
        $json = file_get_contents($url);
        if (!$json) {
            fwrite(STDERR, date('Y-m-d H:i:s') . ' ' . "EnergyManager: failed to read Temperatures\n");
            return;
        }
        $json = json_decode($json, true);
        $this->temp = [];
        $mean = 0;
        for ($i = 0; $i < count($json['hourly']['time']); $i++) {
            $dt = DateTime::createFromFormat('Y-m-d\\TH:i', $json['hourly']['time'][$i], new DateTimeZone('GMT'));
            $dt->setTimezone(new DateTimeZone('Europe/Berlin'));
            $this->temp[$dt->format(DATE_H)] = $json['hourly']['temperature_2m'][$i];
            $mean += $this->temp[$dt->format(DATE_H)];
            if ($i % 24 == 23) {
                $dt->modify('-12 hours');
                $this->temp_means[$dt->format('Y-m-d')] = $mean / 24;
                $mean = 0;
            }
        }

        $this->planing_status &= ~TEMP_OK;
    }



    /**
     * Refreshes the heatpump power consumption array
     * 
     * @return void
     */
    private function refresh_heatpump()
    {
        $this->refresh_temp();
        $dt = new DateTime();
        if (($dt->getTimestamp() - $this->heatpump_update) < $this->heatpump_settings['refresh'])
            return;
        $this->heatpump_update = $dt->getTimestamp();
        fwrite(STDOUT, date('Y-m-d H:i:s') . ' ' . "EnergyManager: refresh Heatpump\n");

        $this->heatpump = [];
        foreach ($this->temp as $hour => $value) {
            $dt = DateTime::createFromFormat("Y-m-d H", $hour);
            $this->heatpump[$hour] = 0;
            $temp = $this->temp_means[$dt->format('Y-m-d')] ?? -999;
            if ($temp == -999)
                break;
            if ($temp < $this->heatpump_settings['heating_limit']) {
                $t_diff = $this->heatpump_settings['indoor_temp'] - $temp;
                $this->heatpump[$hour] = $this->heatpump_settings['lin_coef'] * $t_diff +
                    $this->heatpump_settings['quad_coef'] * $t_diff * $t_diff; //Numbers from Auswertung_Heizung.R 
            }
        }

        $this->planing_status &= ~HEATPUMP_OK;
    }


    /**
     * Function to refresh prices
     * @return void
     */
    private function refresh_price()
    {
        $dt = new DateTime();
        if (($dt->getTimestamp() - $this->price_update) < 3600)
            return;

        $this->price_update = $dt->getTimestamp();
        fwrite(STDOUT, date('Y-m-d H:i:s') . ' ' . "EnergyManager: refresh price\n");

        $json = file_get_contents(
            sprintf(
                'https://api.awattar.de/v1/marketdata?start=%d&end=%d',
                $dt->getTimestamp() * 1000 - 3600 * 24 * 1000,
                $dt->getTimestamp() * 1000 + 2 * 3600 * 24 * 1000
            )
        );
        if (!$json) {
            fwrite(STDERR, date('Y-m-d H:i:s') . ' ' . "EnergyManager: failed to read price\n");
            return;
        }
        $json = json_decode($json);
        $this->price = [];
        $this->price_min_today = 3000;
        $this->price_min_tomorrow = 3000;
        $today = $dt->format("Y-m-d");
        $dt->modify("+1 day");
        $tomorrow = $dt->format("Y-m-d");
        foreach ($json->data as $x) {
            $dt->setTimestamp($x->start_timestamp / 1000);
            $this->price[$dt->format(DATE_H)] = $x->marketprice;
            if ($dt->format('Y-m-d') == $today && $x->marketprice < $this->price_min_today)
                $this->price_min_today = $x->marketprice;
            if ($dt->format('Y-m-d') == $tomorrow && $x->marketprice < $this->price_min_tomorrow)
                $this->price_min_tomorrow = $x->marketprice;

        }

        $this->planing_status &= ~PRICE_OK;
    }

    /**
     * refreshes_pv
     * @return void
     */
    private function refresh_pv()
    {
        $dt = new DateTime();
        if (($dt->getTimestamp() - $this->pv_update) < $this->pv_settings['refresh'])
            return;
        fwrite(STDOUT, date('Y-m-d H:i:s') . ' ' . "EnergyManager: refresh solarprognose " . ($dt->getTimestamp() - $this->pv_update) . "\n");
        $this->pv_update = $dt->getTimestamp();

        $url = sprintf(
            'https://www.solarprognose.de/web/solarprediction/api/v1?access-token=%s&item=plant&id=%d&type=hourly&_format=json',
            $this->pv_settings['access_token'],
            $this->pv_settings['plant_id']
        );

        if ($this->cached_data)
            $url = "/dev/shm/EnergyManager_solarprognose.json";
        $json_file = file_get_contents($url);

        if (!$json_file) {
            fwrite(STDERR, date('Y-m-d H:i:s') . ' ' . "EnergyManager: failed to read solarprognose\n");
            return;
        }
        $fp = fopen("/dev/shm/EnergyManager_solarprognose_tmp.json", "w");
        fwrite($fp, $json_file);
        fclose($fp);

        $json = json_decode($json_file, true);
        if ($json['status'] != 0) {
            fwrite(STDERR, date('Y-m-d H:i:s') . ' ' . "EnergyManager: failed to read solarprognose " . $json['message'] . "\n");
            if (!file_exists("/dev/shm/EnergyManager_solarprognose.json"))
                return;
            fwrite(STDERR, date('Y-m-d H:i:s') . ' ' . "EnergyManager: continue with cached file\n");
            $json_file = file_get_contents("/dev/shm/EnergyManager_solarprognose.json");
            $json = json_decode($json_file, true);

        }


        $pv = [];
        foreach ($json['data'] as $time => $value) {
            $dt->setTimestamp($time);
            $dt->modify("-1 hour");
            $pv[$dt->format(DATE_H)] = $value[0] * $this->pv_settings['factor'];
        }

        $dt = new DateTime();
        $dt->modify("+1 day");
        if (!isset($pv[$dt->format('Y-m-d 12')])) {
            fwrite(STDERR, date('Y-m-d H:i:s') . ' ' . "EnergyManager: solarprognose invalid data\n");
            return;
        }
        $this->pv = $pv;

        $fp = fopen("/dev/shm/EnergyManager_solarprognose.json", "w");
        fwrite($fp, $json_file);
        fclose($fp);

        $this->planing_status &= ~PV_OK;
    }

    /**
     * Refreshes the BEV values and calculates a charging plan
     * based on PV and prices
     * 
     * @return void
     */
    private function refresh_bev()
    {
        $dt = new DateTime();
        if (($dt->getTimestamp() - $this->bev_update) < $this->bev_settings['refresh'])
            return;

        $this->bev_update = $dt->getTimestamp();
        $json = file_get_contents("http://".$this->bev_settings['ip']."/status");
        if (!$json) {
            fwrite(STDERR, date('Y-m-d H:i:s') . ' ' . "EnergyManager: failed to read BEV\n");
            return;
        }
        $this->bev_status = json_decode($json, true);
        $this->bev = [];
        $bev = $this->bev_status['soc'];
        if ($this->bev_status['time'] < 0.001)
            return; //no car present!
        if ($bev >= $this->bev_status['max'])
            return; //nothing to charge
        $end_time = new DateTime();
        $end_time->modify("+" . $this->bev_status['time'] . " hours");
        /**
         * Four runs:
         * 1. PV + Minimum price -> min in time
         * 2. Minimum price -> min in time
         * 3. PV + Minimum price -> max
         * 4. Minimum price -> max
         */
        for ($j = 0; $j < 4; $j++) {
            //check for prices
            if ($j < 2) {
                $limit = $this->bev_status['min'];
                $prices = $this->get_ordered_price_slice($dt->format(DATE_H), $end_time->format(DATE_H));
            } else {
                $limit = $this->bev_status['max'];
                $prices = $this->get_ordered_price_slice($dt->format(DATE_H));
            }
            if ($bev < $limit) {
                foreach ($prices as $hour => $price) {

                    if (isset($this->bev[$hour]))
                        continue;
                    if (($j == 0 || $j == 2) && ($this->pv[$hour] ?? 0) < 2)
                        continue;
                    $this->bev[$hour] = 2.2;
                    $bev += 100 / 17.9 * 2.2 * $this->hour_left($hour);

                    if ($bev > $limit)
                        break;
                }
            }
        }

    }




    /**
     * Reads the battery via ModusTCP
     * sets 
     * $this->battery
     * @return void
     */
    private function refresh_battery()
    {
        $register = <<<REGISTER
0xC8|200|Actual battery charge (-) / discharge (+) current|A|Float|2|RO|0x03|b1
0xCA|202|PSSB fuse state5|-|Float|2|RO|0x03|b1
0xD0|208|Battery ready flag|-|Float|2|RO|0x03|b1
0xD2|210|Act. state of charge|%|Float|2|RO|0x03|b1
0xD6|214|Battery temperature|°C|Float|2|RO|0x03|b1
0xD8|216|Battery voltage|V|Float|2|RO|0x03|b1
0xFC|252|Total active power (powermeter)|W|Float|2|RO|0x03|b2
0x104|260|Power DC1|W|Float|2|RO|0x03|b2
0x10E|270|Power DC2|W|Float|2|RO|0x03|b2
0x118|280|Power DC3|W|Float|2|RO|0x03|b2
0x402|1026|Battery charge power (AC) setpoint, absolute|W|Float|2|RW|0x03/0x10|b3
0x404|1028|Battery charge current (DC) setpoint, relative|%|Float|2|RW|0x03/0x10|b3
0x406|1030|Battery charge power (AC) setpoint, relative|%|Float|2|RW|0x03/0x10|b3
0x408|1032|Battery charge current (DC) setpoint, absolute|A|Float|2|RW|0x03/0x10|b3
0x40A|1034|Battery charge power (DC) setpoint, absolute|W|Float|2|RW|0x03/0x10|b3
0x40C|1036|Battery charge power (DC) setpoint, relative|%|Float|2|RW|0x03/0x10|b3
0x40E|1038|Battery max. charge power limit, absolute|W|Float|2|RW|0x03/0x10|b3
0x410|1040|Battery max. discharge power limit, absolute|W|Float|2|RW|0x03/0x10|b3
0x412|1042|Minimum SOC|%|Float|2|RW|0x03/0x10|b3
0x414|1044|Maximum SOC|%|Float|2|RW|0x03/0x10|b3
REGISTER;

        $lines = explode("\n", $register);
        $addr = [];
        $type = [];
        $name = [];
        $unit = [];
        $num_reg = [];
        $start_i = [];
        $end_i = [];
        $group = '';
        $i = 0;
        foreach ($lines as $l) {
            $tmp = str_getcsv($l, '|');
            $addr[] = intval($tmp[1]);
            $name[] = $tmp[2];
            $unit[] = $tmp[3];
            $type[] = $tmp[4];
            $num_reg[] = intval($tmp[5]);
            if ($tmp[8] != $group) {
                $group = $tmp[8];
                $start_i[] = $i;
                if ($i > 0)
                    $end_i[] = ($i - 1);
            }
            $i++;
        }
        $end_i[] = $i - 1;

        $this->battery = [];

        $table = "";
        for ($j = 0; $j < count($start_i); $j++) {
            $start = $addr[$start_i[$j]];
            $end = $addr[$end_i[$j]];
            $num = ($end - $start) + $num_reg[$end_i[$j]];

            try {
                // FC 3
                $recData = $this->modbus->readMultipleRegisters($this->battery_settings['plant_id'], $start, $num);
            } catch (Exception $e) {
                // Print error information if any
                fwrite(STDERR, date('Y-m-d H:i:s') . ' ' . "EnergyManager: failed to read modbus: $e\n");
                return;
            }

            for ($i = $start_i[$j]; $i <= $end_i[$j]; $i++) {
                $offset = ($addr[$i] - $start) * 2;
                $num_bytes = $num_reg[$i] * 2;
                $data = array_slice($recData, $offset, $num_bytes);
                $table .= $addr[$i] . "; " . $name[$i] . " (" . $unit[$i] . ", " . $type[$i] . "): ";
                $v = '';
                switch ($type[$i]) {
                    case 'Float':
                        $v = PhpType::bytes2float($data);

                        break;
                    case 'U32':
                        $v = PhpType::bytes2unsignedInt(array_reverse($data), 1);
                        break;
                    case 'S16':
                    case 'U8':
                        $v = PhpType::bytes2signedInt($data);
                        break;
                    case 'String':
                        $v = PhpType::bytes2string($data, 1);
                        break;
                }
                $this->battery_status[$name[$i]] = $v;
                $table .= $v . "\n";
            }

        }
        $dt = new DateTime();
        $dt->setTimestamp($this->battery_last_write);
        $table .= "Last write: " . $dt->format("Y-m-d H:i:s") . "\n";


        $fp = fopen("/dev/shm/EnergyManager_battery.txt", "w");
        fwrite($fp, $table);
        fclose($fp);

        $this->planing_status &= ~BAT_OK;

    }


    /**
     * Gives a factor of what is left from the hour (0-1)
     * @return float
     */
    private function hour_left($hour)
    {
        $dt = new DateTime();
        $dt2 = DateTime::createFromFormat('Y-m-d H:i', $hour . ":00");
        $factor = (3600 - ($dt->getTimestamp() - $dt2->getTimestamp())) / 3600;
        if ($factor < 0)
            $factor = 0;
        if ($factor > 1)
            $factor = 1;
        return $factor;

    }

    /**
     * Calculates the grid flow without battery
     * @param mixed $hour
     * @return float
     */
    private function grid_flow_without_battery($hour)
    {
        return $this->pv[$hour] - $this->house[$hour] - $this->bev[$hour] - $this->heatpump[$hour];
    }

    /**
     * kWh to SOC
     * @param mixed $kwh
     * @return float
     */
    private function kwh2soc($kwh)
    {
        return $kwh / $this->battery_kwh * 100;
    }

    /**
     * SOC to kWh
     * @param mixed $soc
     * @return float
     */
    private function soc2kwh($soc)
    {
        return $soc / 100 * $this->battery_kwh;
    }


    /**
     * Calculates the plan for battery charge
     * 
     * @return void
     */
    public function plan()
    {
        //Check setting status
        if ($this->settings_status) {
            fwrite(STDERR, date('Y-m-d H:i:s') . ' ' . "EnergyManager: Missing setting information " . $this->settings_status . "\n");
            return;
        }
        //Refresh all arrays (if necessary)
        $this->refresh_battery();
        $this->refresh_temp();
        $this->refresh_price();
        $this->refresh_heatpump();
        $this->refresh_pv();
        $this->refresh_bev();

        //Exit when planning information is missing
        if ($this->planing_status) {
            fwrite(STDERR, date('Y-m-d H:i:s') . ' ' . "EnergyManager: Missing planing information " . $this->planing_status . "\n");
            return;
        }

        //Start with the current hour and run until 23 o clock tomorrow
        $dt = new DateTime();
        $tomorrow = new DateTime();
        $tomorrow->modify('+1 day');
        $this->battery_flow = [];
        $this->house = [];
        $day_start = $dt->format(DATE_H);
        $night_start = -1;
        $night_end = -1;
        while ($dt->format(DATE_H) <= $tomorrow->format('Y-m-d 23')) {
            $hour = $dt->format(DATE_H);
            $this->house[$hour] = $this->house['consumption'];
            if (!isset($this->pv[$hour]))
                $this->pv[$hour] = 0;
            if (!isset($this->bev[$hour]))
                $this->bev[$hour] = 0;
            if ($this->pv[$hour] == 0 && $night_start == -1)
                $night_start = $hour;
            if ($this->pv[$hour] > 0 && $night_start != -1 && $night_end == -1)
                $night_end = $hour;
            $this->battery_flow[$hour] = $this->grid_flow_without_battery($hour);
            $dt->modify("+1 hour");
        }

        //move $night_end+1 and $night_start-1
        if ($day_start < $night_start) {
            $night_start = DateTime::createFromFormat("Y-m-d H", $night_start);
            $night_start->modify("-1 hour");
            $night_start = $night_start->format(DATE_H);
        }
        $night_end = DateTime::createFromFormat("Y-m-d H", $night_end);
        $night_end->modify("+1 hour");
        $night_end = $night_end->format(DATE_H);


        //Current houre
        $dt = new DateTime();
        $this->battery = [];
        $this->battery_restrictions = [];
        $this->grid = [];
        $battery_soc = $this->battery_status['Act. state of charge']; //Current value
        $bat = $battery_soc; //Test variable for forward calculations

        //We are during the day with pv production
        if ($night_start > $day_start) {
            $grid = 0;
            $prices = $this->get_ordered_price_slice($day_start, $night_start);
            //1. look for lowest prices to fill battery with pv
            foreach ($prices as $hour => $price) {
                if ($this->battery_flow[$hour] < 0)
                    continue;
                $factor = $this->hour_left($hour);
                $bat += $this->kwh2soc($this->battery_flow[$hour]) * $factor;
                if ($bat > 100) {
                    $kwh = ($bat - 100) / 100 * $this->battery_kwh / $factor;
                    $this->battery_flow[$hour] -= $kwh;
                    $this->grid[$hour] = $kwh;
                    $grid += $kwh;
                    $bat = 100;
                    if ($this->battery_flow[$hour] < 0.02)
                        $this->battery_restrictions[$hour] = 'no charge';

                }
            }


            //2. Active discharge during highes prices in the morning
            if (
                $battery_soc > $this->battery_settings['md_min_soc'] &&
                $grid > $this->battery_settings['md_min_grid'] && $dt->format('H') < '10'
            ) {
                $bat = $battery_soc;
                $prices = $this->get_ordered_price_slice($dt->format(DATE_H), $dt->format('Y-m-d 10'), true);
                $soc_rate = $this->battery_settings['md_soc_rate'];
                foreach ($prices as $hour => $price) {
                    if (($price - $this->price_min_today) < $this->battery_settings['md_min_price'])
                        break; //no discharge below e.g. 80 €/MWh

                    $factor = $this->hour_left($hour);
                    $this->battery_restrictions[$hour] = 'active discharge';
                    $bat -= $soc_rate * $factor;
                    $this->battery_flow[$hour] = -$this->soc2kwh($soc_rate);
                    $this->grid[$hour] = -$this->battery_flow[$hour] + $this->grid_flow_without_battery($hour);
                    $grid -= $this->grid[$hour] * $factor;
                    if ($grid < $this->battery_settings['md_min_grid'])
                        break;
                    if ($bat < $this->battery_settings['md_min_soc'])
                        break;
                }

                //second run! -- recheck for timeslots to charge
                $prices = $this->get_ordered_price_slice($dt->format('Y-m-d 10'), $night_start);
                //look for lowest prices to fill battery with pv
                foreach ($prices as $hour => $price) {
                    if (($this->grid[$hour] ?? 0) > 0) {
                        $this->battery_flow[$hour] += $this->grid[$hour]; //flow to battery...
                        $this->grid[$hour] = 0;
                        unset($this->battery_restrictions[$hour]);
                    }
                    $factor = $this->hour_left($hour);
                    $bat += $this->kwh2soc($this->battery_flow[$hour]) * $factor;

                    if ($bat > 100) {
                        $kwh = ($bat - 100) / 100 * $this->battery_kwh / $factor;
                        $this->battery_flow[$hour] -= $kwh;
                        $this->grid[$hour] = $kwh;
                        $bat = 100;
                        if ($this->battery_flow[$hour] < 0.02)
                            $this->battery_restrictions[$hour] = 'no charge';
                    }
                }
            } //END Active discharge morging

            // Save as battery charge plan for the current day      
            while ($dt->format(DATE_H) < $night_start) {
                $hour = $dt->format(DATE_H);
                $battery_soc += $this->kwh2soc($this->battery_flow[$hour]) * $this->hour_left($hour);
                $this->battery[$hour] = $battery_soc;
                if ($battery_soc >= 99) {
                    if (($this->battery_restrictions[$hour] ?? '') == 'no charge')
                        unset($this->battery_restrictions[$hour]);
                }
                $dt->modify("+1 hour");
            }

        } 
        //End of "during the day with pv production"
        //$dt is now a timestamp of the night

        // Expected SOC at start of Night
        $bat = $battery_soc; //Test variable for forward calculations
        $prices = $this->get_ordered_price_slice($night_start, $night_end, true);
        //1. look for lowest prices to stop battery discharge
        foreach ($prices as $hour => $price) {
            if ($this->battery_flow[$hour] < 0) {
                $factor = $this->hour_left($hour);
                $bat += $this->kwh2soc($this->battery_flow[$hour]) * $factor;
                $this->grid[$hour] = 0; //no grid discharge battery...
                if ($bat < 10) {
                    $kwh = (10 - $bat) / 100 * $this->battery_kwh / $factor;
                    $this->battery_flow[$hour] += $kwh;
                    $this->grid[$hour] -= $kwh;
                    $bat = 10;
                    $this->battery_restrictions[$hour] = 'no discharge';
                }
            }
        }

        $dt_end = DateTime::createFromFormat(DATE_H, $night_end);
        //2. Active Discharge during highest prices in the evening/morning
        if ($bat > $this->battery_settings['ed_min_soc_morning']) {
            $prices = $this->get_ordered_price_slice($night_start, $dt_end->format('Y-m-d 10'), true);
            $soc_rate = $this->battery_settings['ed_soc_rate'];
            foreach ($prices as $hour => $price) {
                if (($price - $this->price_min_tomorrow) < $this->battery_settings['ed_min_price'])
                    break;
                if ($this->pv[$hour] > 1.5)
                    continue;

                $this->battery_restrictions[$hour] = 'active discharge';
                $bat -= $soc_rate * $this->hour_left($hour);
                $this->battery_flow[$hour] = -$soc_rate / 100 * $this->battery_kwh;
                $this->grid[$hour] = -$this->battery_flow[$hour] + $this->grid_flow_without_battery($hour);
                if ($bat < $this->battery_settings['ed_min_soc_morning'])
                    break;
            }

        }

        // Save as battery charge plan for the current night
        while ($dt->format(DATE_H) < $night_end) {
            $hour = $dt->format(DATE_H);
            $battery_soc += $this->kwh2soc($this->battery_flow[$hour]) * $this->hour_left($hour);
            $this->grid[$hour] ??= 0;
            if ($battery_soc > 100) {
                $kwh = ($battery_soc - 100) / 100 * $this->battery_kwh / $this->hour_left($hour);
                $this->battery_flow[$hour] -= $kwh;
                $this->grid[$hour] += $kwh;
                $battery_soc = 100;
            }

            $this->battery[$hour] = $battery_soc;
            $dt->modify("+1 hour");
        }

        // Tomorrow 
        // $dt is now the timestamp of tomorrow morning
        $bat = $battery_soc;
        $dt2 = clone $dt;
        // 1. Check for battery restrictions from evening discharge
        while ($dt2->format(DATE_H) < $dt_end->format('Y-m-d 10')) {
            $hour = $dt2->format(DATE_H);
            if (isset($this->battery_restrictions[$hour])) {
                $bat -= $this->kwh2soc($this->battery_flow[$hour]);
            }
            $dt2->modify("+1 hour");
        }

        // 2. Look for lowest prices to charge battery
        $prices = $this->get_ordered_price_slice($night_end);
        foreach ($prices as $hour => $price) {
            if (isset($this->battery_restrictions[$hour]))
                continue;
            $this->grid[$hour] = 0;
            if ($this->battery_flow[$hour] < 0)
                continue;
            $bat += $this->kwh2soc($this->battery_flow[$hour]);

            if ($bat > 100) {
                $kwh = ($bat - 100) / 100 * $this->battery_kwh;
                $this->battery_flow[$hour] -= $kwh;
                $this->grid[$hour] += $kwh;
                $bat = 100;
                if ($this->battery_flow[$hour] < 0.02)
                    $this->battery_restrictions[$hour] = 'no charge';
            }
        }

        // Save as battery charge plan for tomorrow
        while ($dt->format(DATE_H) <= $tomorrow->format("Y-m-d 23")) {
            $hour = $dt->format(DATE_H);
            $battery_soc += $this->kwh2soc($this->battery_flow[$hour]);
            $this->grid[$hour] ??= 0;
            if ($battery_soc > 100) {
                $kwh = ($battery_soc - 100) / 100 * $this->battery_kwh;
                $this->battery_flow[$hour] -= $kwh;
                $this->grid[$hour] += $kwh;
                $battery_soc = 100;
            }
            $this->battery[$hour] = $battery_soc;
            $dt->modify("+1 hour");

        }

        //Table output for debugging
        $dt = new DateTime();
        $table = "Time: " . $dt->format('Y-m-d H:i:s') . "\n";
        $table .= "   Date       Price PV    BEV   House HP    Bat   Grid  Bat   Temp  Restr.\n";
        $table .= "  Y-m-d H     €/MWh kWh   kWh   kWh   kWh   kWh   kWh   %     °C\n";
        foreach ($this->battery as $hour => $bat) {
            $table .= ($hour . " ");
            $table .= (
                sprintf(
                    "%5.0f %5.1f %5.1f %5.1f %5.1f %5.1f %5.1f %5.0f %5.1f %s\n",
                    $this->price[$hour] ?? NAN,
                    $this->pv[$hour],
                    $this->bev[$hour],
                    $this->house[$hour],
                    $this->heatpump[$hour],
                    $this->battery_flow[$hour],
                    $this->grid[$hour] ?? 0,
                    $bat,
                    $this->temp[$hour] ?? NAN,
                    $this->battery_restrictions[$hour] ?? '-'
                )
            );
        }
        $dt->setTimestamp($this->pv_update);
        $table .= "PV: " . $dt->format('Y-m-d H:i:s') . "\n";
        $dt->setTimestamp($this->price_update);
        $table .= "Price: " . $dt->format('Y-m-d H:i:s') . "\n";
        $table .= "DayStart: $day_start - NightStart: $night_start - NightEnd: $night_end\n";


        $fp = fopen("/dev/shm/EnergyManager_plan.txt", "w");
        fwrite($fp, $table);
        fclose($fp);

        if ($this->verbous)
            print $table;


    }

    /**
     * Get array with planning information
     * 
     * @param string $date Datesting in format 'Y-m-d H'
     * @return array
     */
    public function get_planning_info($date = null)
    {
        if (is_null($date)) {
            $dt = new DateTime();
            $date = $dt->format(DATE_H);
        }
        return array(
            'PV' => $this->pv[$date] ?? NAN,
            'Bat' => $this->battery[$date] ?? NAN,
            'BatFlow' => $this->battery_flow[$date] ?? NAN,
            'Preis' => $this->price[$date] ?? NAN,
            'Netz' => $this->grid[$date] ?? NAN,
            'Temp' => $this->temp[$date] ?? NAN,
            'BEV' => $this->bev[$date] ?? 0,
            'HP' => $this->heatpump[$date] ?? NAN,
            'HOUSE' => $this->house[$date] ?? NAN
        );

    }


    /**
     * Run-Function... refreshes plan and runs commands
     * Switches on BEV charger
     * Runs commands on battery via modbusTCP
     * 
     * @return boolean
     */
    public function run($verbous = false)
    {
        $this->verbous = $verbous;
        $dt = new DateTime();
        $hour = $dt->format(DATE_H); //current hour

        if ($this->verbous)
            print ("PLAN: " . $dt->format('H:i:s') . ": " . $hour . "\n");

        $this->plan(); //Refresh

        if ($this->planing_status)
            return false;

        //Battery
        $value = false;
        $bat_external_active = ($dt->getTimestamp() - $this->battery_last_write) < 10;
        if (isset($this->battery_restrictions[$hour])) {
            $current_value = $this->battery_status['Actual battery charge (-) / discharge (+) current'] * $this->battery_status['Battery voltage'];
            switch ($this->battery_restrictions[$hour]) {
                case 'no charge':
                    $value = 0;
                    if ($bat_external_active && $this->battery_status['Total active power (powermeter)'] > 100)
                        $value = false; //more demand than production -> disable external battery management
                    if (!$bat_external_active && $current_value > 0)
                        $value = false; //still more demand than production -> keep external battery manangement disabled   
                    if ($this->battery_status['Act. state of charge'] > 98)
                        $value = false;
                    break;
                case 'no discharge':
                    $value = 0;
                    if ($this->price[$hour] < 0)
                        $value = -1000; //Charge Battery on negativ prices
                    $value += rand(-10000, 10000) / 1000; //add a random -10 ... 10 value to have different values for write
                    //not sure, if this is really required...    
                    break;
                case 'active discharge':
                    $value = -$this->battery_flow[$hour] * 1000 + rand(-10000, 10000) / 1000; //default value in W + randdom -10 .. 10

                    if ($this->battery_status['Total active power (powermeter)'] > 100) { //more demand than discharge
                        if ($current_value > $value)
                            $value = $current_value;
                        $value += $this->battery_status['Total active power (powermeter)']; //increase discharge to meet demand
                    } elseif (
                        abs($this->battery_status['Total active power (powermeter)']) < 100 &&
                        $current_value > $value
                    ) {
                        $value = $current_value; //keep increased discharge
                    }

                    //Error detection
                    //Battery power should reach set setpoint within (Power setpoint)/(max Gradient) (e.g. 2000/42 ) seconds.
                    //If battery does not react, disable external battery manangement for a while ...
                    if ($current_value < $value * 0.5) {
                        $this->battery_error_count++;
                        if ($this->battery_error_count > 10) {
                            if ($this->battery_error_count == 11)
                                fwrite(STDERR, date('Y-m-d H:i:s') . ' ' . "EnergyManager: Battery does not react " .
                                    sprintf("Current: %.0f -- Set: %.0f", $current_value, $value) . "...\n");
                            $value = false;
                            if ($this->battery_error_count > 30) {
                                $this->battery_error_count = 0;
                                fwrite(STDERR, date('Y-m-d H:i:s') . ' ' . "EnergyManager: Battery retry...\n");

                            }

                        }
                    } else
                        $this->battery_error_count = 0;

                    break;
            }
        }

        if (!($value === false)) {
            $this->battery_last_write = $dt->getTimestamp();
            try {
                // FC 16
                $recData = $this->modbus->writeMultipleRegister($this->battery_settings['plant_id'], 1034, [$value], ['FLOAT']);
            } catch (Exception $e) {
                // Print error information if any
                fwrite(STDERR, date('Y-m-d H:i:s') . ' ' . "EnergyManager: failed to write modbus: $e\n");
            }
        }

        //BEV
        if ($this->bev[$hour] > 0 && ($dt->getTimestamp() - $this->bev_last_command) > 60) {
            //Switch on charger
            $json = file_get_contents("http://".$this->bev_settings['ip']."/cmd?t=5");
            $charger = json_decode($json, true);
            if ($charger['status'] != 3)
                fwrite(STDERR, date('Y-m-d H:i:s') . ' ' . "EnergyManager: Failed to swich on BEV charger\n");
            else
                $this->bev_last_command = $dt->getTimestamp();
        }
        return true;

    }


}
