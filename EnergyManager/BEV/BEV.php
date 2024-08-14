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


    abstract public function charge($kw, $duration);

    public function plan(\EnergyManager\PV\PV $pv_obj, \EnergyManager\Price\Price $price_obj){
        if(! $this->refresh()) return false;
        $time= $this->time();
        $this->plan=[];
        $soc = $this->soc;
        $pv=$pv_obj->getProduction();
        if ($this->charge_time > 0.001 && $soc < $this->max_soc) {
            $end = $time + $this->charge_time * 3600;
            $start = floor($time/3600)*3600;
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
                        if (($j == 0 || $j == 2) && ($pv[$hour] ?? 0) < 0.8 * $this->min_kw)
                            continue;

                        $kw = $this->min_kw;
                        if ($j == 0 || $j == 2) {
                            if (0.8 * $pv[$hour] > $kw)
                                $kw = 0.8 * $pv[$hour];
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

    public function getPlan(){
        return $this->plan;
    }

    public function getSOC(){
        return $this->soc;
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

