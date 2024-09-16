<?php

namespace EnergyManager\Heatpump;

use Exception;

require_once 'PHPModbus/ModbusMasterTcp.php';

class HeatpumpDimplexDaikin extends HeatpumpQuadratic
{
    private \ModbusMasterTcp $modbus; //phpmodbus Object
    private array $daikin = [];
    private array $dimplex = [];

    public function __construct($settings, \EnergyManager\Temp\Temp $temp_obj)
    {
        $this->defaults = [
            "ip" => null,
            "daikin" => [],
            "daikin_timetable" => [],
            "daikin_delta" => 2,
            "max_kw"=>3,
            "plant_id" => 1,
            "heating_limit" => 15,
            "indoor_temp" => 20,
            "lin_coef" => null,
            "quad_coef" => null,
            "refresh" => 60,
            'price_enhance_delta' => -30, /*enhance when price 30 €/MWh below mean*/
            'price_enhance' => 10, /*enhance when price is blow 10 €/MWh */
            'price_disable_delta' => 20, /*disable when price 20 €/MWh above mean*/
            'price_disable' => 70 /* Do not disable when price is below 70 €/MWh */
        ];
        $this->setSettings($settings);
        $this->modbus = new \ModbusMasterTcp($this->settings['ip'], "1502");
        $this->temp_obj = $temp_obj;

    }

    private function readDaikin($ip, $path)
    {
        try {
            $l = file_get_contents('http://' . $ip . '/aircon/' . $path);
            $v = explode(",", $l);
            $values = [];
            foreach ($v as $v1) {
                $v1 = explode("=", $v1);
                $values[$v1[0]] = $v1[1];
            }
            if ($values['ret'] == 'OK')
                return $values;
            else
                return false;
        } catch (Exception $e) {
            return false;
        }
    }

    private function setDaikin($ip, $pow, $temp)
    {
        try {
            $req = 'http://' . $ip . '/aircon/set_control_info?pow=' . $pow . '&mode=4&stemp=' . $temp . '&shum=0&f_rate=A&f_dir=0';
            $l = file_get_contents($req);
            $v = explode(",", $l);
            $values = [];
            foreach ($v as $v1) {
                $v1 = explode("=", $v1);
                $values[$v1[0]] = $v1[1];
            }
            if ($values['ret'] == 'OK') {
                fwrite(STDOUT, date('Y-m-d H:i:s') . ' ' . "EnergyManager: Daikin $ip set $pow/$temp OK\n");
                $this->update -= $this->settings['refresh'];//force update next call
                return $values;
            } else {
                fwrite(STDOUT, date('Y-m-d H:i:s') . ' ' . "EnergyManager: Daikin $ip set did not return OK\n");
                return false;

            }
        } catch (Exception $e) {
            fwrite(STDOUT, date('Y-m-d H:i:s') . ' ' . "EnergyManager: Daikin $ip set failed: " . $e->getMessage() . "\n");
            return false;
        }
    }

    public function refresh()
    {
        $dt = new \DateTime();
        if (($dt->getTimestamp() - $this->update) < $this->settings['refresh'])
            return true;
        $this->update = $dt->getTimestamp();
        //Daikin
        $count = 0;
        $htemp = 0;
        $pow = 0;
        foreach ($this->settings['daikin'] as $name => $ip) {
            $v = $this->readDaikin($ip, 'get_control_info');
            if ($v !== false) {
                $this->daikin[$name]['pow'] = $v['pow'];
                $pow += $v['pow'];
                $this->daikin[$name]['stemp'] = $v['stemp'];
            } else
                return false;

            $v = $this->readDaikin($ip, 'get_sensor_info');
            if ($v !== false) {
                $this->daikin['otemp'] = $v['otemp'];
                $htemp += $v['htemp'];
                $count++;
                $this->daikin[$name]['htemp'] = $v['htemp'];
            } else
                return false;


        }
        if ($count > 0) {
            $this->daikin['htemp'] = $htemp / $count;
        }
        $this->daikin['pow'] = $pow;

        //Modbus
        $modbus_felder = [
            "Kollektor 1",
            "Kollektor 2",
            "Solarspeicher unten",
            "Solarspeicher oben",
            "Brauchwasserspeicher",
            "Pufferspeicher",
            "Solar Vorlauf",
            "Solar Ruecklauf",
            "Innen",
            "Heizung Vorlauf",
            "Heizung Ruecklauf",
            "WP Vorlauf",
            "WP Ruecklauf",
            "Solarpumpe",
            "Zirkulationspumpe",
            "Pufferladung",
            "Eigenstrom",
            "Eigenstrom+",
            "WP-Sperre",
            "Daikin Küche",
            "Daikin Wohnzimmer",
            "Daikin Flur",
            "Haus Miniumtemperatur",
            "Awattar Anhebung",
            "Awattar Sperre",
            "Daikin Soll",
            "Daikin Soll+"
        ];

        try {
            $recData = $this->modbus->readMultipleRegisters($this->settings['plant_id'], 0, 27 * 2);
            for ($i = 0; $i < 27; $i++) {
                $data = array_reverse(array_slice($recData, 4 * $i, 4));
                $this->dimplex[$modbus_felder[$i]] = \PhpType::bytes2float($data, 1);
            }
        } catch (Exception $e) {
            return false;
        }

        $fp = fopen("/dev/shm/EnergyManager_DimplexDaikin.txt", "w");
        foreach ($this->dimplex as $k => $v)
            fwrite($fp, sprintf("%s: %.1f\n", $k, $v));

        foreach ($this->daikin as $k => $v) {
            if (is_array($v)) {
                foreach ($v as $k2 => $v2)
                    fwrite($fp, sprintf("%s-%s: %.1f\n", $k, $k2, $v2));
            } else
                fwrite($fp, sprintf("%s: %.1f\n", $k, $v));
        }
        fclose($fp);

        return $this->temp_obj->refresh();

    }

    public function setMode(string $mode)
    {
        $temp = $this->dimplex['Daikin Soll'];
        $temp_enhance = $this->dimplex['Daikin Soll+'];
        $write_modbus = false;
        //Checking Timetable
        $daikin_timetable = [];
        $dt = new \DateTime();
        $dow = $dt->format('N');
        $hour = $dt->format('H:i');
        foreach ($this->settings['daikin'] as $name => $ip) {
            if (!isset($this->settings['daikin_timetable'][$name]))
                continue;
            //timetable format:
            //'Küche'=>[['1-5','05:00-06:30',21],...]
            foreach ($this->settings['daikin_timetable'][$name] as $v) {
                $v2 = explode("-", $v[0]);
                if ($v2[0] > $dow || $v2[1] < $dow)
                    continue;
                $v2 = explode("-", $v[1]);
                if ($v2[0] > $hour || $v2[1] < $hour)
                    continue;
                if (!$this->daikin[$name]['pow'] && $this->daikin[$name]['htemp'] > $v[2])
                    continue;
                $daikin_timetable[$name] = ['ip' => $ip, 'temp' => $v[2]];
            }
        }
        foreach ($daikin_timetable as $name => $v) {
            if ($this->daikin[$name]['pow'] && $this->daikin[$name]['stemp'] == $v['temp'])
                continue;
            $this->setDaikin($v['ip'], 1, $v['temp']);
        }


        switch ($mode) {
            case "enhanced":
                foreach ($this->settings['daikin'] as $name => $ip) {
                    if (isset($daikin_timetable[$name]))
                        continue;
                    if (!($this->dimplex['Daikin ' . $name] ?? true))
                        continue;
                    if ($this->daikin[$name]['pow'] && $this->daikin[$name]['stemp'] == $temp_enhance)
                        continue;
                    $this->setDaikin($ip, 1, $temp_enhance);
                }
                if (!$this->dimplex['Eigenstrom']) {
                    $write_modbus = true;
                    $values = [1, 0, 0];
                }

                break;
            case "disabled":
                foreach ($this->settings['daikin'] as $name => $ip) {
                    if (isset($daikin_timetable[$name]))
                        continue;
                    if (!$this->daikin[$name]['pow'])
                        continue;
                    if (!($this->dimplex['Daikin ' . $name] ?? true))
                        continue;
                    $this->setDaikin($ip, 0, $temp);
                }
                if (!$this->dimplex['WP-Sperre']) {
                    $write_modbus = true;
                    $values = [0, 0, 1];
                }

                break;
            default:
                foreach ($this->settings['daikin'] as $name => $ip) {
                    if (isset($daikin_timetable[$name]))
                        continue;
                    if (!($this->dimplex['Daikin ' . $name] ?? true))
                        continue;
                    if ($this->daikin[$name]['pow'] && $this->daikin[$name]['stemp'] == $temp)
                        continue;
                    $this->setDaikin($ip, 1, $temp);
                }
                if ($this->dimplex['Eigenstrom'] || $this->dimplex['WP-Sperre']) {
                    $write_modbus = true;
                    $values = [0, 0, 0];
                }

        }


        if ($write_modbus) {
            try {
                $this->modbus->writeMultipleRegister(
                    $this->settings['plant_id'],
                    103,
                    $values,
                    ['INT', 'INT', 'INT']
                );
                fwrite(STDOUT, date('Y-m-d H:i:s') . ' ' .
                    sprintf("EnergyManager: Modbus write [%d, %d, %d] \n", $values[0], $values[1], $values[2]));
                $this->update -= $this->settings['refresh'];//force update next call
            } catch (Exception $e) {
                fwrite(STDOUT, date('Y-m-d H:i:s') . ' ' . "EnergyManager: Modbus write failed: " . $e->getMessage() . "\n");
            }

        }

    }


    public function plan(array $free_prod, \EnergyManager\Price\Price $price_obj): bool
    {
        if (!$this->refresh())
            return false;
        $dt = new \DateTime();
        $dt->setTimestamp($this->time());
        $this->plan = [];
        $this->mode = [];
        $daily = $this->temp_obj->getDaily();
        $mean_price = $price_obj->getMean(24);

        $house_min_temp = ($this->dimplex['Haus Miniumtemperatur'] + ($this->daikin['pow'] ? $this->settings['daikin_delta'] : 0));
        $prices = $price_obj->get_ordered_price_slice($this->time(), $this->time() + 24 * 3600, true);
        $disabled = 0;
        $kw = $this->getKw($this->temp_obj->getMean());
        $max_disabled = ($this->settings['max_kw'] - $kw) / $this->settings['max_kw'] * 24;
        foreach ($prices as $hour => $price) {
            if ($this->daikin['htemp'] < $house_min_temp)
                break;
            if (($price - $mean_price) < $this->settings['price_disable_delta'] || $price < $this->settings['price_disable'])
                break;
            $this->mode[$hour] = 'disabled';
            $disabled++;
            if ($disabled > $max_disabled)
                break;
        }

        $prices = $price_obj->get_ordered_price_slice($this->time(), $this->time() + 24 * 3600, false);
        $enhanced = 0;
        foreach ($prices as $hour => $price) {
            if (($price - $mean_price) > $this->settings['price_enhance_delta'] && $price > $this->settings['price_enhance'])
                break;
            $this->mode[$hour] = 'enhanced';
            $enhanced++;
        }

        foreach ($free_prod as $hour => $prod) {
            $dt->setTimestamp($hour);
            $temp = $daily[$dt->format('Y-m-d')] ?? -999;
            if ($temp == -999)
                continue;
            $this->plan[$hour] = $this->getKw($temp);
            if (
                $prod <= 0 && ($this->daikin['htemp'] - $house_min_temp) > 1
                && $disabled < $max_disabled && ($this->mode[$hour] ?? '') != 'disabled'
            ) {
                $this->mode[$hour] = 'disabled';
                $disabled++;
            }
            if (($this->mode[$hour] ?? '') == 'disabled')
                $this->plan[$hour] = 0;
            if (($this->mode[$hour] ?? '') == 'enhanced')
                $this->plan[$hour] *= 1.5;
        }

        //rescale to expected power consumption...
        $kwh = 0;
        foreach ($this->plan as $v) {
            if (is_null(($v)))
                $v = 0;
            $kwh += $v;
        }
        if($kwh){
            foreach ($this->plan as $hour => $v) {
                $this->plan[$hour] *= $kw / $kwh * 24;
            }
        }

        return true;
    }
}