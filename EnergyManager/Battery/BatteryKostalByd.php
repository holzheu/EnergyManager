<?php

namespace EnergyManager\Battery;

require_once 'PHPModbus/ModbusMasterTcp.php';


class BatteryKostalByd extends Battery
{
    private \ModbusMasterTcp $modbus; //phpmodbus Object
    private int $error_count = 0; //error count

    private array $status; //array with battery values read via modbusTCP
    private float $last_write = 0; //Last write

    /**
     * Constructor
     * @param array $settings
     */
    public function __construct(array $settings)
    {
        $this->defaults = [
            "ip" => null,
            "plant_id" => 71
        ];

        $this->setSettings($settings);

        $this->modbus = new \ModbusMasterTcp($this->settings['ip'], "1502");
        $this->kwh = 0;
        //read capacity
        while (!$this->kwh) {
            try {
                // FC 3
                $recData = $this->modbus->readMultipleRegisters($this->settings['plant_id'], 1068, 2);
                $this->kwh = \PhpType::bytes2float($recData) / 1000;

            } catch (\Exception $e) {
                // Print error information if any
                fwrite(STDERR, date('Y-m-d H:i:s') . ' ' . "EnergyManager: failed to read battery capacity: $e\n");
                sleep(10);
            }

        }
        fwrite(STDOUT, date('Y-m-d H:i:s') . ' ' . "EnergyManager: Successfully set battery with ip $settings[ip] and " . $this->kwh . " kWh\n");
    }

    /**
     * Implementation of refresh method
     * @return bool
     */
    public function refresh()
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


        $table = "";
        for ($j = 0; $j < count($start_i); $j++) {
            $start = $addr[$start_i[$j]];
            $end = $addr[$end_i[$j]];
            $num = ($end - $start) + $num_reg[$end_i[$j]];

            try {
                // FC 3
                $recData = $this->modbus->readMultipleRegisters($this->settings['plant_id'], $start, $num);
            } catch (\Exception $e) {
                // Print error information if any
                fwrite(STDERR, date('Y-m-d H:i:s') . ' ' . "EnergyManager: failed to read modbus: $e\n");
                return false;
            }

            for ($i = $start_i[$j]; $i <= $end_i[$j]; $i++) {
                $offset = ($addr[$i] - $start) * 2;
                $num_bytes = $num_reg[$i] * 2;
                $data = array_slice($recData, $offset, $num_bytes);
                $table .= $addr[$i] . "; " . $name[$i] . " (" . $unit[$i] . ", " . $type[$i] . "): ";
                $v = '';
                switch ($type[$i]) {
                    case 'Float':
                        $v = \PhpType::bytes2float($data);

                        break;
                    case 'U32':
                        $v = \PhpType::bytes2unsignedInt(array_reverse($data), 1);
                        break;
                    case 'S16':
                    case 'U8':
                        $v = \PhpType::bytes2signedInt($data);
                        break;
                    case 'String':
                        $v = \PhpType::bytes2string($data, 1);
                        break;
                }
                $this->status[$name[$i]] = $v;
                $table .= $v . "\n";
            }

        }
        $dt = new \DateTime();
        $dt->setTimestamp($this->last_write);
        $table .= "Last write: " . $dt->format("Y-m-d H:i:s") . "\n";


        $fp = fopen("/dev/shm/EnergyManager_battery.txt", "w");
        fwrite($fp, $table);
        fclose($fp);
        $this->soc = $this->status['Act. state of charge'];
        return true;


    }

    /**
     * Implemation of refresh method
     * @param mixed $mode
     * @param mixed $kw
     * @throws \Exception 
     * @return void
     */
    public function setMode($mode, $kw = null)
    {
        if (!is_null($kw))
            $kw = -$kw; //change sign to fitt Kostal 
        $current_kw = $this->status['Actual battery charge (-) / discharge (+) current'] * $this->status['Battery voltage'] / 1000;
        $current_grid = $this->status['Total active power (powermeter)'] / 1000;
        $external_active = (time() - $this->last_write) < 10;
        switch ($mode) {
            case 'active discharge':
                if ($current_grid > 0.1) { //more demand than discharge
                    if ($current_kw > $kw)
                        $kw = $current_kw;
                    $kw += $current_grid; //increase discharge to meet demand
                } elseif (
                    abs($current_grid) < 0.1 && $current_kw > $kw
                ) {
                    $kw = $current_kw; //keep increased discharge
                }

                //Error detection
                //Battery power should reach set setpoint within (Power setpoint)/(max Gradient) (e.g. 2000/42 ) seconds.
                //If battery does not react, disable external battery manangement for a while ...
                if ($current_kw < $kw * 0.2) {
                    $this->error_count++;
                    if ($this->error_count > 5) {
                        if ($this->error_count == 6)
                            fwrite(STDERR, date('Y-m-d H:i:s') . ' ' . "EnergyManager: Battery does not react " .
                                sprintf("Current: %.0f -- Set: %.0f", $current_kw, $kw) . "...\n");
                        $kw = null;
                        if ($this->error_count > 30) {
                            $this->error_count = 0;
                            fwrite(STDERR, date('Y-m-d H:i:s') . ' ' . "EnergyManager: Battery retry...\n");
                        }

                    }
                } else
                    $this->error_count = 0;
                break;
            case 'no charge':
                $kw = 0;
                if ($external_active && $current_grid > 0.1)
                    $kw = null; //more demand than production -> disable external battery management
                if (!$external_active && $current_kw > 0)
                    $kw = null; //still more demand than production -> keep external battery manangement disabled   
                if ($this->soc > 98)
                    $kw = null;
                break;
            case 'no discarge':
                $kw = 0;
                if ($external_active && $current_grid < 0)
                    $kw = null; //Grid feed in --- charge battery instead
                if (!$external_active && $current_kw < -0.05)
                    $kw = null; //still less demand then production
                break;
            case 'active charge':
                break;
            default:
                throw new \Exception('Unknown mode ' . $mode);

        }

        if (!is_null($kw)) {
            $this->last_write = time();
            try {
                // FC 16
                $recData = $this->modbus->writeMultipleRegister($this->settings['plant_id'], 1034, [$kw * 1000], ['FLOAT']);
            } catch (\Exception $e) {
                // Print error information if any
                fwrite(STDERR, date('Y-m-d H:i:s') . ' ' . "EnergyManager: failed to write modbus: $e\n");
            }

        }


    }


}
