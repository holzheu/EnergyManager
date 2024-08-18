<?php
/**
 * Energy Manager 
 * 
 * @author Stefan Holzheu <stefan.holzheu@uni-bayreuth.de>
 * 
 * Status information is written to files in /dev/shm/
 * 
 */

namespace EnergyManager;

require_once __DIR__ . "/autoload.php";

define("BAT_OK", 0x1);
define("PV_OK", 0x2);
define("PRICE_OK", 0x4);
define("TEMP_OK", 0x8);
define("HEATPUMP_OK", 0x10);
define("HOUSE_OK", 0x20);
define("BEV_OK", 0x40);
class EnergyManager extends Device
{
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
    private PV\PV $pv_obj;
    private Battery\Battery $bat_obj;
    private Price\Price $price_obj;
    private House\House $house_obj;
    private ?Heatpump\Heatpump $hp_obj=null;
    private ?BEV\BEV $bev_obj=null;

    /**
     * Constructor of EnergyManager
     * @param \EnergyManager\PV\PV $pv PV forecast object
     * @param \EnergyManager\Battery\Battery $bat Battery object
     * @param \EnergyManager\Price\Price $price Price object
     * @param \EnergyManager\House\House $house House object
     * @param ?\EnergyManager\BEV\BEV|null $bev BEV object
     * @param ?\EnergyManager\Heatpump\Heatpump|null $hp Heatpump object
     */
    public function __construct(PV\PV $pv, Battery\Battery $bat, Price\Price $price, House\House $house, BEV\BEV $bev = null, Heatpump\Heatpump $hp = null)
    {
        $this->pv_obj = $pv;
        $this->bat_obj = $bat;
        $this->price_obj = $price;
        $this->house_obj = $house;
        if( $bev!==null) $this->bev_obj = $bev;
        if( $hp!==null) $this->hp_obj = $hp;
    }



    /**
     * Refreshes all objects
     * 
     * @return void
     */
    public function refresh()
    {
        if ($this->price_obj->refresh())
            $this->planing_status &= ~PRICE_OK;
        $this->price = $this->price_obj->getPrice();

        if ($this->pv_obj->refresh())
            $this->planing_status &= ~PV_OK;
        $this->pv = $this->pv_obj->getProduction();

        if ($this->bat_obj->refresh())
            $this->planing_status &= ~BAT_OK;

        $this->house_obj->plan($this->getFreeProduction(''), $this->price_obj);
        $this->house=$this->house_obj->getPlan();

        if (!is_null($this->hp_obj)) {
            if ($this->hp_obj->plan($this->getFreeProduction('house'), $this->price_obj)) {
                $this->planing_status &= ~HEATPUMP_OK;
                $this->planing_status &= ~TEMP_OK;
                $this->heatpump = $this->hp_obj->getPlan();
                $this->temp = $this->hp_obj->getTemp();
            }
        } else
            $this->planing_status &= ~HEATPUMP_OK;


        if (!is_null($this->bev_obj)) {
            if ($this->bev_obj->plan($this->getFreeProduction('heatpump'), $this->price_obj)) {
                $this->bev = $this->bev_obj->getPlan();
                $this->planing_status &= ~BEV_OK;
            }
        } else
            $this->planing_status &= ~BEV_OK;





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

    public function getFreeProduction(string $with = 'house')
    {
        $hour = $this->full_hour($this->time());
        $res = [];
        for ($i = 0; $i < 24; $i++) {
            $res[$hour] = $this->pv[$hour]??0;
            switch($with){
                case 'heatpump':
                    $res[$hour] -= $this->heatpump[$hour];
                case 'house':
                    $res[$hour]-=$this->house[$hour];
            }
            $hour += 3600;
        }
        return $res;
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
            $soc += $this->bat_obj->kwh2soc($this->battery_flow[$hour]) * $this->hour_left($hour);
            $this->grid[$hour] ??= 0;
            if ($soc < 5) {
                $diff = $this->bat_obj->soc2kwh(5 - $soc);
                $this->battery_flow[$hour] += $diff / $this->hour_left($hour);
                $this->grid[$hour] -= $diff / $this->hour_left($hour);
                $soc = 5;
            } else if ($soc > 100) {
                $diff = $this->bat_obj->soc2kwh($soc - 100);
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
            $soc += $this->bat_obj->kwh2soc($this->battery_flow[$hour]) * $factor;
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

            $this->battery_flow[$hour] = $this->bat_obj->soc2kwh($soc_rate);
            $this->battery_active_flow[$hour] = $this->bat_obj->soc2kwh($soc_rate);
            $this->calc_gridflow($hour);
            $grid -= $this->bat_obj->soc2kwh($soc_rate) * $factor;
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

            if (($this->grid[$hour] ?? 0) > 0) {
                $this->battery_flow[$hour] += $this->grid[$hour]; //flow to battery...
                $this->grid[$hour] = 0;
                unset($this->battery_restrictions[$hour]);
            }
            if ($this->battery_flow[$hour] < 0)
                continue;
            $factor = $this->hour_left($hour);
            $soc += $this->bat_obj->kwh2soc($this->battery_flow[$hour]) * $factor;
            if ($soc >= 100) {
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
            $this->battery_active_flow[$hour] = -$this->bat_obj->soc2kwh($soc_rate);
            $soc -= $soc_rate * $factor;
            $this->battery_flow[$hour] = -$this->bat_obj->soc2kwh($soc_rate);
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
    public function plan()
    {
        //Avoid planing 10 s before new hour
        if ($this->hour_left($this->time()) < 10 / 3600)
            return false;

        //Refresh all objects
        $this->refresh();

        //Exit when planning information is missing
        if ($this->planing_status) {
            fwrite(STDERR, date('Y-m-d H:i:s') . ' ' . "EnergyManager: Missing planing information " . $this->planing_status . "\n");
            return false;
        }

        // Calculate expected Production/Consumption the next 24 hours
        $now = $this->full_hour($this->time());
        $hour = $now;

        $prod = 0;
        $cons = 0;
        while ($hour < $now + 24 * 3600) {
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

        $dt = new \DateTime();
        $dt->setTimestamp($hour);
        $h = intval($dt->format('H'));
        if (($prod - $cons) > $this->bat_obj->getSettings()['min_grid']) {
            if ($h < 18)
                $this->find_no_charge($soc, $now, $now + (18 - $h) * 3600);
            if ($h < 11 && $h >= 5)
                $this->find_active_discharge($min_soc, $prod - $cons, 'md', $now, $now + (11 - $h) * 3600);
            elseif ($h >= 11 || $h < 5)
                $this->find_active_discharge($min_soc, $prod - $cons, 'ed', $now, $now + 12 * 3600);

        } elseif (($cons - $prod) > $this->bat_obj->getSettings()['min_grid']) {
            $this->find_no_discharge($soc, $now, $now + 24 * 3600);
            $this->find_active_charge($soc, $cons - $prod, $now, $now + 24 * 3600);
        }
        $this->save_charge_plan($now, $now + 24 * 3600, $soc);

        //Table output for debugging
        $dt->setTimestamp($this->time());
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
                    $this->heatpump[$hour] ?? NAN,
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
