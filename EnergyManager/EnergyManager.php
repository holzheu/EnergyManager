<?php
/**
 * Energy Manager 
 * 
 * @author Stefan Holzheu <stefan.holzheu@uni-bayreuth.de>
 * 
 * Status information is written to files in /dev/shm/
 * 
 */

require_once (dirname(__FILE__) . "/Battery.php");
require_once (dirname(__FILE__) . "/BEV.php");
require_once (dirname(__FILE__) . "/Heatpump.php");
require_once (dirname(__FILE__) . "/House.php");
require_once (dirname(__FILE__) . "/Price.php");
require_once (dirname(__FILE__) . "/PV.php");
require_once (dirname(__FILE__) . "/Temp.php");

define("BAT_OK", 0x1);
define("PV_OK", 0x2);
define("PRICE_OK", 0x4);
define("TEMP_OK", 0x8);
define("HEATPUMP_OK", 0x10);
define("HOUSE_OK", 0x20);
define("BEV_OK", 0x40);
class EnergyManager
{
    private float $time; //Time variable... helpful for debugging
    private $planing_status = BAT_OK | PV_OK | PRICE_OK | TEMP_OK | HEATPUMP_OK | BEV_OK;
    private $pv = []; //Array with estimated PV-production in kWh per hour
    private $price = []; //Array with prices in €/MWh per hour
    private $bev = [];  //Array with estimated BEV consumption in kWh per hour
    private $battery = []; //Array with SOC per hours
    private $battery_flow = []; //Array with battery flow in kWh (+ charge battery, - discharge battery)
    private $battery_restrictions = []; //Array with restrictions
    private $battery_active_flow = []; //Array with battery active flows (+ charge battery, - discharge)
    private $temp = []; //Array with temperatures
    private $grid = []; //Array with estimated grid-flow in kWh per hour (+ grid feed in, - grid power supply)
    private $house = []; //Array with estimated house consumption in kWh per hour
    private $heatpump = []; //Array with estimated heat pump consumption in kWh per hour

    //Objects 
    private PV $pv_obj;
    private Battery $bat_obj;
    private BEV $bev_obj;
    private Price $price_obj;
    private House $house_obj;
    private Heatpump $hp_obj;
    private Temp $temp_obj;


    /**
     * EnergyManager
     *
     * constructor 
     */
    public function __construct(PV $pv, Battery $bat, Price $price, House $house, BEV $bev = null, Heatpump $hp = null, Temp $temp = null)
    {
        $this->pv_obj = $pv;
        $this->bat_obj = $bat;
        $this->price_obj = $price;
        $this->bev_obj = $bev;
        $this->house_obj = $house;
        $this->hp_obj = $hp;
        $this->temp_obj = $temp;
    }


    /**
     * Returns the timestamp of the full hour
     * @param float $time
     * @return int
     */
    private function full_hour(float $time): int
    {
        return (int) floor($time / 3600) * 3600;
    }

    /**
     * Refreshes all objects
     * 
     * @return void
     */
    private function refresh()
    {
        if ($this->price_obj->refresh())
            $this->planing_status &= ~PRICE_OK;
        $this->price = $this->price_obj->getPrice();

        if ($this->pv_obj->refresh())
            $this->planing_status &= ~PV_OK;
        $this->pv = $this->pv_obj->getProduction();

        if ($this->bat_obj->refresh())
            $this->planing_status &= ~BAT_OK;

        if (!is_null($this->bev_obj)) {
            $this->bev_obj->refresh();
            $soc = $this->bev_obj->getSoc();
            if ($this->bev_obj->getChargeTime() > 0.001 && $soc < $this->bev_obj->getMaxSoc()) {
                $end = $this->time() + $this->bev_obj->getChargeTime() * 3600;
                $start = $this->full_hour($this->time());
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
                        $limit = $this->bev_obj->getMinSoc();
                        $prices = $this->price_obj->get_ordered_price_slice($start, $end);
                    } else {
                        $limit = $this->bev_obj->getMaxSoc();
                        $prices = $this->price_obj->get_ordered_price_slice($start);
                    }
                    if ($soc < $limit) {
                        foreach ($prices as $hour => $price) {

                            if (isset($this->bev[$hour]))
                                continue;
                            if (($j == 0 || $j == 2) && ($this->pv[$hour] ?? 0) < 0.8 * $this->bev_obj->getMinKw())
                                continue;

                            $kw = $this->bev_obj->getMinKw();
                            if ($j == 0 || $j == 2) {
                                if (0.8 * $this->pv[$hour] > $kw)
                                    $kw = 0.8 * $this->pv[$hour];
                                if ($kw > $this->bev_obj->getMaxKw())
                                    $kw = $this->bev_obj->getMaxKw();
                            }
                            $this->bev[$hour] = $kw;
                            $soc += 100 / $this->bev_obj->getKWh() * $this->bev_obj->getMaxKw() * $this->hour_left($hour);

                            if ($soc > $limit)
                                break;
                        }
                    }
                }
            }
            $this->planing_status &= ~BEV_OK;


        } else
            $this->planing_status &= ~BEV_OK;


        if (!is_null($this->temp_obj)) {
            if ($this->temp_obj->refresh())
                $this->planing_status &= ~TEMP_OK;

            $this->temp = $this->temp_obj->getHourly();
        } else
            $this->planing_status &= ~TEMP_OK;

        if (!is_null($this->hp_obj)) {
            if ($this->hp_obj->refresh())
                $this->planing_status &= ~HEATPUMP_OK;
            $dt = new DateTime();
            $this->heatpump = [];
            $daily = $this->temp_obj->getDaily();
            foreach ($this->temp_obj->getHourly() as $hour => $value) {
                $dt->setTimestamp($hour);
                $temp = $daily[$dt->format('Y-m-d')] ?? -999;
                if ($temp == -999)
                    break;
                $this->heatpump[$hour] = $this->hp_obj->getKw($temp);
            }
        } else
            $this->planing_status &= ~HEATPUMP_OK;




    }

    /**
     * Returns the current time of the EnergeyManager
     * normally this is just time()
     * But for debugging it is helpful to calculate the plan for
     * timestamps different from time()
     * @return float|int
     */
    private function time(){
        if( $this->time === null) return time();
        return $this->time;
    }

    /**
     * Gives a factor of what is left from the hour (0-1)
     * @return float
     */
    private function hour_left(float $hour)
    {
        $hour = $this->full_hour($hour);
        $now = $this->time();
        $factor = (3600 - ($now - $hour)) / 3600;
        if ($factor < 0)
            $factor = 0;
        if ($factor > 1)
            $factor = 1;
        return $factor;

    }

    /**
     * Calculates the consumption
     * @param float $hour
     * @return float
     */
    private function consumption(float $hour)
    {
        $hour = $this->full_hour($hour);
        return $this->house[$hour] + ($this->bev[$hour] ?? 0) + ($this->heatpump[$hour] ?? 0);
    }


    private function calc_gridflow(float $hour)
    {
        $hour = $this->full_hour($hour);
        $this->grid[$hour] = $this->grid_flow_without_battery($hour) - $this->battery_flow[$hour];
    }

    /**
     * Calculates the grid flow without battery
     * @param float $hour
     * @return float
     */
    private function grid_flow_without_battery(float $hour)
    {
        $hour = $this->full_hour($hour);
        return $this->pv[$hour] - $this->consumption($hour);
    }

    /**
     * kWh to SOC
     * @param mixed $kwh
     * @return float
     */
    private function kwh2soc(float $kwh)
    {
        return $kwh / $this->bat_obj->getCapacity() * 100;
    }

    /**
     * SOC to kWh
     * @param mixed $soc
     * @return float
     */
    private function soc2kwh(float $soc)
    {
        return $soc / 100 * $this->bat_obj->getCapacity();
    }


    /**
     * Save the charge plan
     * @param float $from Start hour 
     * @param float $to End hour (not included)
     * @param float $soc Start SOC
     * @return float End SOC
     */
    private function save_charge_plan(float $from, float $to, float $soc)
    {
        $hour = $this->full_hour($from);
        $to = $this->full_hour($to);
        while ($hour < $to) {
            $soc += $this->kwh2soc($this->battery_flow[$hour]) * $this->hour_left($hour);
            $this->grid[$hour] ??= 0;
            if ($soc < 5) {
                $diff = $this->soc2kwh(5 - $soc);
                $this->battery_flow[$hour] += $diff / $this->hour_left($hour);
                $this->grid[$hour] -= $diff / $this->hour_left($hour);
                $soc = 5;
            } else if ($soc > 100) {
                $diff = $this->soc2kwh($soc - 100);
                $this->battery_flow[$hour] -= $diff / $this->hour_left($hour);
                $this->grid[$hour] += $diff / $this->hour_left($hour);
                $soc = 100;

            }
            $this->battery[$hour] = $soc;
            if ($soc >= 99) {
                if (($this->battery_restrictions[$hour] ?? '') == 'no charge')
                    unset($this->battery_restrictions[$hour]);
            }
            $hour += 3600;
        }
        return $soc;
    }

    /**
     * Calculates battery discharge starting from $soc
     * When SOC drops below 10% hour is marked with 'no discharge'
     * 
     * @param float $soc Start SOC
     * @param float $from Start hour
     * @param float $to End hour (not included)
     * @return array [End SOC, grid consumption]
     */
    private function find_no_discharge(float $soc, float $from, float $to = null)
    {
        $grid = 0;
        $prices = $this->price_obj->get_ordered_price_slice($from, $to, true); //highes prices first
        foreach ($prices as $hour => $price) {
            if ($this->battery_flow[$hour] > 0)
                continue; //no Battery discharge
            $factor = $this->hour_left($hour);
            $soc += $this->kwh2soc($this->battery_flow[$hour]) * $factor;
            $this->grid[$hour] = 0; //no grid discharge battery...
            if ($soc < 10) {
                $kwh = (10 - $soc) / 100 * $this->bat_obj->getCapacity() / $factor;
                $this->battery_flow[$hour] += $kwh;
                $this->grid[$hour] -= $kwh;
                $grid += $kwh;
                $soc = 10;
                $this->battery_restrictions[$hour] = 'no discharge';
            }
        }


        return [$soc, $grid];

    }

    /**
     * Find active charge hours
     * @param float $soc Start SOC
     * @param float $grid Grid demand before active charge
     * @param float $from Start hour
     * @param float $to End hour (not included)
     * @return void
     */
    private function find_active_charge(float $soc, float $grid, float $from, float $to = null)
    {
        $soc_rate = $this->bat_obj->getSettings()['charge_power'] / $this->bat_obj->getCapacity() * 100;
        if ($soc_rate <= 0)
            return;

        $prices = $this->price_obj->get_ordered_price_slice($from, $to, true);
        $max_price = array_values($prices)[0];

        $prices = $this->price_obj->get_ordered_price_slice($from, $to);

        foreach ($prices as $hour => $price) {
            if (($max_price - $price) < $this->bat_obj->getSettings()['charge_min_price_diff'])
                break;
            if ($price > $this->bat_obj->getSettings()['charge_max_price'])
                break;
            $factor = $this->hour_left($hour);
            $this->battery_restrictions[$hour] = 'active charge';
            $soc += $soc_rate * $factor;

            $this->battery_flow[$hour] = $this->soc2kwh($soc_rate);
            $this->battery_active_flow[$hour] = $this->soc2kwh($soc_rate);
            $this->calc_gridflow($hour);
            $grid -= $this->soc2kwh($soc_rate) * $factor;
            if ($grid < 0)
                break;
            if ($soc > 100)
                break;
        }

    }


    /**
     * Looks for cheapest hours to charge the battery. 
     * When battery is 100% hour are marked with 'no charge'
     * @param float $soc Start SOC
     * @param float $from Start hour
     * @param float $to End hour (not included)
     * @return float expected grid feed in
     */
    private function find_no_charge(float $soc, float $from, float $to = null)
    {
        $grid = 0;
        $prices = $this->price_obj->get_ordered_price_slice($from, $to);
        //1. look for lowest prices to fill battery with pv
        foreach ($prices as $hour => $price) {
            if ($this->battery_flow[$hour] < 0)
                continue;
            if (($this->grid[$hour] ?? 0) > 0) {
                $this->battery_flow[$hour] += $this->grid[$hour]; //flow to battery...
                $this->grid[$hour] = 0;
                unset($this->battery_restrictions[$hour]);
            }
            $factor = $this->hour_left($hour);
            $soc += $this->kwh2soc($this->battery_flow[$hour]) * $factor;
            if ($soc > 100) {
                $kwh = ($soc - 100) / 100 * $this->bat_obj->getCapacity() / $factor;
                $this->battery_flow[$hour] -= $kwh;
                $this->grid[$hour] = $kwh;
                $grid += $kwh;
                $soc = 100;
                if ($this->battery_flow[$hour] < 0.02)
                    $this->battery_restrictions[$hour] = 'no charge';

            }
        }
        return $grid;
    }



    /**
     * Active discharge during highes prices in the morning (md) or evening (ed)
     * @param float $soc Start SOC
     * @param float $grid Expected grid feed in
     * @param string $type one of 'md' or 'ed'
     * @param float $from Start hour
     * @param float $to End hour (not included)
     * @return float Expected SOC after activ discharge
     */
    private function find_active_discharge(float $soc, float $grid, string $type, float $from, float $to = null)
    {
        $prices = $this->price_obj->get_ordered_price_slice($from, $to, true);
        $soc_rate = $this->bat_obj->getSettings()[$type . '_soc_rate'];
        if ($soc_rate <= 0)
            return $soc; //No active discharge

        if ($soc < $this->bat_obj->getSettings()[$type . '_min_soc'])
            return $soc;

        foreach ($prices as $hour => $price) {
            if ($type == 'md') {
                if (($price - $this->price_obj->getMin(12)) < $this->bat_obj->getSettings()[$type . '_min_price'])
                    break; //no discharge below e.g. 80 €/MWh
            } else {
                if (($price - $this->price_obj->getMin(24)) < $this->bat_obj->getSettings()[$type . '_min_price'])
                    break;
                if ($this->pv[$hour] > 1.5)
                    continue;
            }

            $factor = $this->hour_left($hour);
            $this->battery_restrictions[$hour] = 'active discharge';
            $this->battery_active_flow[$hour] = -$this->soc2kwh($soc_rate);
            $soc -= $soc_rate * $factor;
            $this->battery_flow[$hour] = -$this->soc2kwh($soc_rate);
            $this->grid[$hour] = -$this->battery_flow[$hour] + $this->grid_flow_without_battery($hour);
            $grid -= $this->grid[$hour] * $factor;
            if ($grid < $this->bat_obj->getSettings()['min_grid'])
                break;
            if ($soc < $this->bat_obj->getSettings()[$type . '_min_soc'])
                break;
        }
        return $soc;
    }

    /**
     * Performs the planning for the next 24 hours
     * Normally plan is called without argument. 
     * Then the planning is carried out by the current time
     * @return bool|string
     */
    public function plan($time=null)
    {
        if ($time === null){
            $time = time();
            unset($this->time);
        }
        else $this->time=$time;
        //Avoid planing 10 s before new hour
        if ($this->hour_left($time) < 10 / 3600)
            return false;

        //Refresh all objects
        $this->refresh();

        //Exit when planning information is missing
        if ($this->planing_status) {
            fwrite(STDERR, date('Y-m-d H:i:s') . ' ' . "EnergyManager: Missing planing information " . $this->planing_status . "\n");
            return false;
        }

        // Calculate expected Production/Consumption the next 24 hours
        $now = $this->full_hour($time);
        $hour = $now;

        $prod = 0;
        $cons = 0;
        while ($hour < $now + 24 * 3600) {
            $this->house[$hour] = $this->house_obj->getKw($hour);
            if (!isset($this->pv[$hour]))
                $this->pv[$hour] = 0;
            if (!isset($this->bev[$hour]))
                $this->bev[$hour] = 0;
            $prod += $this->pv[$hour];
            $cons += $this->consumption($hour);
            $this->battery_flow[$hour] = $this->grid_flow_without_battery($hour);
            $hour += 3600;
        }

        $this->battery = [];
        $this->battery_restrictions = [];
        $this->grid = [];
        $soc = $this->bat_obj->getSOC(); //Current value
        $this->save_charge_plan($now, $now + 12 * 3600, $soc); //Save current plan to get min soc 
        $min_soc = min($this->battery);
        $min_soc = min($soc, $min_soc);

        $dt = new DateTime();
        $dt->setTimestamp($hour);
        if (($prod - $cons) > $this->bat_obj->getSettings()['min_grid']) {
            $this->find_no_charge($soc, $now, $now + 12 * 3600);
            if ($dt->format('H') < '11' && $dt->format('H') >= '05')
                $this->find_active_discharge($min_soc, $prod - $cons, 'md', $now, $now + 6 * 3600);
            elseif ($dt->format('H') >= '11' || $dt->format('H') < '05')
                $this->find_active_discharge($min_soc, $prod - $cons, 'ed', $now, $now + 12 * 3600);
        } elseif (($cons - $prod) > $this->bat_obj->getSettings()['min_grid']) {
            $this->find_no_discharge($soc, $now, $now + 12 * 3600);
            $this->find_active_charge($soc, $cons - $prod, $now, $now + 12 * 3600);
        }
        $this->save_charge_plan($now, $now + 24 * 3600, $soc);

        //Table output for debugging
        $table = "Time: " . $dt->format('Y-m-d H:i:s') . "\n";
        $table .= "   Date       Price PV    BEV   House HP    Bat   Grid  Bat   Temp  Restr.\n";
        $table .= "  Y-m-d H     €/MWh kWh   kWh   kWh   kWh   kWh   kWh   %     °C\n";
        foreach ($this->battery as $hour => $bat) {
            $dt->setTimestamp($hour);
            $table .= $dt->format(DATE_H) . " ";
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
        $table .= sprintf("Production: %.1f kWh - Consumption: %.1f kWh\n", $prod, $cons);

        $fp = fopen("/dev/shm/EnergyManager_plan.txt", "w");
        fwrite($fp, $table);
        fclose($fp);
        return $table;


    }





    /**
     * Get array with planning information
     * 
     * @param  float $hour 
     * @return array
     */
    public function get_planning_info($hour = null)
    {
        if (is_null($hour)) {
            $hour = time();
        }
        $hour = $this->full_hour($hour);
        return array(
            'PV' => $this->pv[$hour] ?? NAN,
            'Bat' => $this->battery[$hour] ?? NAN,
            'BatFlow' => $this->battery_flow[$hour] ?? NAN,
            'Preis' => $this->price[$hour] ?? NAN,
            'Netz' => $this->grid[$hour] ?? NAN,
            'Temp' => $this->temp[$hour] ?? NAN,
            'BEV' => $this->bev[$hour] ?? 0,
            'HP' => $this->heatpump[$hour] ?? NAN,
            'HOUSE' => $this->house[$hour] ?? NAN
        );

    }


    /**
     * Run-Function... refreshes plan and 
     * runs commands on BEV and battery
     * 
     * @return boolean
     */
    public function run()
    {
        $this->plan(); //Refresh

        if ($this->planing_status)
            return false;

        $hour = $this->full_hour(time());
        //Battery
        if (isset($this->battery_restrictions[$hour])) {
            $this->bat_obj->setMode($this->battery_restrictions[$hour], $this->battery_active_flow[$hour] ?? 0);
        }


        //BEV
        if ($this->bev[$hour] > 0) {
            $this->bev_obj->charge($this->bev[$hour], 2 / 60);
        }
        return true;

    }


}
