<?php
/**
 * Abstract BEV class
 */
namespace EnergyManager\BEV;

abstract class BEV extends \EnergyManager\Device
{
    protected $kwh = 20;
    protected $soc = 30;
    protected $min_kw = 2.2;
    protected $max_kw = 2.2;

    protected $min_soc = 55;
    protected $max_soc = 85;
    protected $charge_time = 2; //hours

    protected $plan = [];

    protected $is_present = true;


    abstract public function charge($kw, $duration);

    public function plan(array $free_prod, \EnergyManager\Price\Price $price_obj)
    {
        if (!$this->refresh())
            return false;
        $time = $this->time();
        $this->plan = [];
        if (!$this->is_present)
            return true;
        $soc = $this->soc;
        if ($soc < $this->max_soc) {
            $end = $time + $this->charge_time * 3600;
            $start = floor($time / 3600) * 3600;
            if ($this->charge_time < 0.001) {
                $dt = new \DateTime($this->settings['time_back'] ?? '17:00');
                $start = floor($dt->getTimestamp() / 3600) * 3600;
                if (($time - $start) > 3600)
                    $start += 3600 * 24;
            }
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
                    $limit = $this->min_soc;
                    $prices = $price_obj->get_ordered_price_slice($start, $end);
                } else {
                    $limit = $this->max_soc;
                    $prices = $price_obj->get_ordered_price_slice($start);
                }
                if ($soc < $limit) {
                    foreach ($prices as $hour => $price) {

                        if (isset($this->plan[$hour]))
                            continue;
                        if (($j == 0 || $j == 2) && ($free_prod[$hour] ?? 0) < $this->min_kw)
                            continue;

                        $kw = $this->min_kw;
                        if ($j == 0 || $j == 2) {
                            if ($free_prod[$hour] > $kw)
                                $kw = $free_prod[$hour];
                            if ($kw > $this->max_kw)
                                $kw = $this->max_kw;
                        }
                        $this->plan[$hour] = $kw;
                        $soc += 100 / $this->kwh * $kw * $this->hour_left($hour);

                        if ($soc > $limit)
                            break;
                    }
                }
            }
        }
        return true;

    }

    public function getPlan()
    {
        return $this->plan;
    }

    public function getSOC()
    {
        return $this->soc;
    }

    public function setSOC($soc)
    {
        $this->soc = $soc;
    }

    public function setChargeTime($charge_time)
    {
        $this->charge_time = $charge_time;
        $this->is_present = ($this->charge_time > 0);
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

