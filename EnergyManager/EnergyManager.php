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
    private $planing_status = BAT_OK | PV_OK | PRICE_OK | TEMP_OK | HEATPUMP_OK | BEV_OK;
    private $pv = []; //Array with estimated PV-production in kWh per hour
    private $price = []; //Array with prices in €/MWh per hour
    private $bev = [];  //Array with estimated BEV consumption in kWh per hour
    private $battery = []; //Array with SOC hours
    private $battery_flow = []; //Array with battery flow in kWh (+ charge battery, - discharge battery)
    private $battery_restrictions = []; //Array with restrictions
    private $battery_active_flow = []; //Array with battery active flows (+ charge battery, - discharge)
    private $temp = []; //Array with temperatures
    private $grid = []; //Array with estimated grid-flow in kWh per hour (+ grid feed in, - grid power supply)
    private $house = []; //Array with estimated house consumption in kWh per hour
    private $heatpump = []; //Array with estimated heat pump consumption in kWh per hour

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
                $end_time = new DateTime();
                $end_time->modify("+" . $this->bev_obj->getChargeTime() . " hours");
                /**
                 * Four runs:
                 * 1. PV + Minimum price -> min in time
                 * 2. Minimum price -> min in time
                 * 3. PV + Minimum price -> max
                 * 4. Minimum price -> max
                 */
                $dt = new DateTime();
                for ($j = 0; $j < 4; $j++) {
                    //check for prices
                    if ($j < 2) {
                        $limit = $this->bev_obj->getMinSoc();
                        $prices = $this->price_obj->get_ordered_price_slice($dt->format(DATE_H), $end_time->format(DATE_H));
                    } else {
                        $limit = $this->bev_obj->getMaxSoc();
                        $prices = $this->price_obj->get_ordered_price_slice($dt->format(DATE_H));
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

            $this->heatpump = [];
            $daily = $this->temp_obj->getDaily();
            foreach ($this->temp_obj->getHourly() as $hour => $value) {
                $dt = DateTime::createFromFormat("Y-m-d H", $hour);
                $temp = $daily[$dt->format('Y-m-d')] ?? -999;
                if ($temp == -999)
                    break;
                $this->heatpump[$hour] = $this->hp_obj->getKw($temp);
            }
        } else
            $this->planing_status &= ~HEATPUMP_OK;




    }


    /**
     * Gives a factor of what is left from the hour (0-1)
     * @return float
     */
    private function hour_left(string $hour)
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
     * Calculates the consumption
     * @param string $hour
     * @return float
     */
    private function consumption(string $hour)
    {
        return $this->house[$hour] + ($this->bev[$hour] ?? 0) + ($this->heatpump[$hour] ?? 0);
    }


    /**
     * Calculates the grid flow without battery
     * @param string $hour
     * @return float
     */
    private function grid_flow_without_battery(string $hour)
    {
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


    private function save_charge_plan(DateTime &$dt, string $to, float $soc)
    {
        while ($dt->format(DATE_H) < $to) {
            $hour = $dt->format(DATE_H);
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
            $dt->modify("+1 hour");
        }
        return $soc;
    }

    /**
     * Calculates battery discharge starting from $soc
     * When SOC drops below 10% hour is marked with 'no discharge'
     * 
     * @param float $soc Start SOC
     * @param string $from Start hour
     * @param string $to End hour (not included)
     * @return array End SOC, grid consumption
     */
    private function find_no_discharge(float $soc, string $from, string $to = null)
    {
        $grid=0;
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
                $grid+=$kwh;
                $soc = 10;
                $this->battery_restrictions[$hour] = 'no discharge';
            }
        }
 

       return [$soc,$grid];

    }

    private function find_active_charge(float $soc, float $grid, string $from, string $to = null){
        $bat_settings = $this->bat_obj->getSettings();
        $soc_rate = $bat_settings['charge_power']/$this->bat_obj->getCapacity()*100;
       
        $prices = $this->price_obj->get_ordered_price_slice($from, $to,true);
        foreach ($prices as $hour => $max_price){
            break;
        }
        $prices = $this->price_obj->get_ordered_price_slice($from, $to);

        foreach ($prices as $hour => $price) {
            if(($max_price-$price)<50)
                break;
            $factor = $this->hour_left($hour);
            $this->battery_restrictions[$hour] = 'active charge';
            $soc += $soc_rate * $factor;
            $this->battery_flow[$hour] += $this->soc2kwh($soc_rate);
            $this->battery_active_flow[$hour] = $this->soc2kwh($soc_rate);
            $this->grid[$hour] -= $this->soc2kwh($soc_rate);
            $grid -= $this->soc2kwh($soc_rate) * $factor;
            if ($grid < 0)
                break;
            if($soc>100) break;
        }

    }


    /**
     * Looks for cheapest hours to charge the battery. 
     * When battery is 100% hour are marked with 'no charge'
     * @param float $soc Start SOC
     * @param string $from Start hour
     * @param string $to End hour (not included)
     * @return float expected grid feed in
     */
    private function find_charge(float $soc, string $from, string $to = null)
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
     * @param string $from Start hour
     * @param string $to End hour (not included)
     * @return float Expected SOC after activ discharge
     */
    private function find_active_discharge(float $soc, float $grid, string $type, string $from, string $to = null)
    {
        $prices = $this->price_obj->get_ordered_price_slice($from, $to, true);
        $bat_settings = $this->bat_obj->getSettings();

        $soc_rate = $bat_settings[$type . '_soc_rate'];
        foreach ($prices as $hour => $price) {
            if ($type == 'md') {
                if (($price - $this->price_obj->getMin_today()) < $bat_settings[$type . '_min_price'])
                    break; //no discharge below e.g. 80 €/MWh
            } else {
                if (($price - $this->price_obj->getMin_tomorrow()) < $bat_settings[$type . '_min_price'])
                    break;
                if ($this->pv[$hour] > 1.5)
                    continue;
            }

            $factor = $this->hour_left($hour);
            $this->battery_restrictions[$hour] = 'active discharge';
            $this->battery_active_flow[$hour] = - $this->soc2kwh($soc_rate);
            $soc -= $soc_rate * $factor;
            $this->battery_flow[$hour] = -$this->soc2kwh($soc_rate);
            $this->grid[$hour] = -$this->battery_flow[$hour] + $this->grid_flow_without_battery($hour);
            $grid -= $this->grid[$hour] * $factor;
            if ($grid < $bat_settings['min_grid'])
                break;
            if ($soc < $bat_settings[$type . '_min_soc'])
                break;
        }
        return $soc;
    }

    /**
     * Calculates the plan for battery charge
     * 
     * @return mixed
     */
    public function plan()
    {
        //Refresh all objects
        $this->refresh();

        //Exit when planning information is missing
        if ($this->planing_status) {
            fwrite(STDERR, date('Y-m-d H:i:s') . ' ' . "EnergyManager: Missing planing information " . $this->planing_status . "\n");
            return false;
        }

        $dt = new DateTime();
        $tomorrow = new DateTime();
        $tomorrow->modify('+1 day');
        $two_day_ahead = new DateTime();
        $two_day_ahead->modify('+2 day');
        $this->battery_flow = [];
        $this->house = [];
        $day_start = $dt->format(DATE_H);
        $prod_today = 0;
        $cons_today = 0;
        $prod_tomorrow = 0;
        $cons_tomorrow = 0;
        $night_start = -1;
        $night_end = -1;
        while ($dt->format(DATE_H) <= $two_day_ahead->format('Y-m-d 06')) {
            $hour = $dt->format(DATE_H);
            $this->house[$hour] = $this->house_obj->getKw($hour);
            if (!isset($this->pv[$hour]))
                $this->pv[$hour] = 0;
            if (!isset($this->bev[$hour]))
                $this->bev[$hour] = 0;
            if ($this->pv[$hour] == 0 && $night_start == -1)
                $night_start = $hour;
            if ($this->pv[$hour] > 0 && $night_start != -1 && $night_end == -1)
                $night_end = $hour;
            if ($night_end == -1) {
                $prod_today += $this->pv[$hour];
                $cons_today += $this->consumption($hour);
            } else {
                $prod_tomorrow += $this->pv[$hour];
                $cons_tomorrow += $this->consumption($hour);
            }
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


        //Current hour
        $dt = new DateTime();
        $this->battery = [];
        $this->battery_restrictions = [];
        $this->grid = [];
        $soc = $this->bat_obj->getSOC(); //Current value
        $bat_settings = $this->bat_obj->getSettings();

        //We are during the day with pv production
        if ($night_start > $day_start) {
            $grid = $this->find_charge($soc, $day_start, $night_start);
            //Active discharge during highes prices in the morning
            if ($soc > $bat_settings['md_min_soc'] && $grid > $bat_settings['min_grid'] && $dt->format('H') < '10') {
                $soc_moring = $this->find_active_discharge($soc, $grid, 'md', $dt->format(DATE_H), $dt->format('Y-m-d 10'));
                //second run! -- recheck for timeslots to charge
                $this->find_charge($soc_moring, $dt->format('Y-m-d 10'), $night_start);
            }

            if ($prod_today < $cons_today) {
                [$soc_moring,$grid]=$this->find_no_discharge($soc, $dt->format(DATE_H), $night_end);
            }

            // Save as battery charge plan for the current day   
           $soc = $this->save_charge_plan($dt, $night_start, $soc);
           
           if($soc_moring<= 10){
                $dt=new DateTime();
                $this->find_active_charge($soc, $grid, $dt->format(DATE_H), $night_end);
                $soc = $this->save_charge_plan($dt, $night_start, $this->bat_obj->getSOC());
           }

        }
        //End of "during the day with pv production"
        //$dt is now a timestamp of the night

        [$soc_moring, $grid] = $this->find_no_discharge($soc, $night_start, $night_end);
        //2. Active Discharge during highest prices in the evening
        if (
            ($prod_tomorrow - $cons_tomorrow) > $bat_settings['min_grid'] &&
            $soc_moring > $bat_settings['ed_min_soc']
        ) {
            $soc_moring = $this->find_active_discharge($soc, $prod_tomorrow - $cons_tomorrow, 'ed', $night_start, $night_end);
        }

        // Save as battery charge plan for the current night
        $soc = $this->save_charge_plan($dt, $night_end, $soc);

        // Tomorrow 
        // $dt is now the timestamp of tomorrow morning
        if (($prod_tomorrow - $cons_tomorrow) < $bat_settings['min_grid']) {
            $this->find_no_discharge($soc, $night_end);
        } else {
            $grid = $this->find_charge($soc, $night_end);
            $soc_morning = $this->find_active_discharge($soc, $grid, 'md', $night_end, $tomorrow->format('Y-m-d 10'));
            $grid = $this->find_charge($soc_morning, $night_end); //2. run    
        }
        // Save as battery charge plan for tomorrow
        $this->save_charge_plan($dt, $tomorrow->format('Y-m-d 24'), $soc);

        //TODO: Move heat pump to feed in hours/cheap hours
        //TODO: Plan active charge when demand exceeds production


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
        $table .= "DayStart: $day_start - NightStart: $night_start - NightEnd: $night_end\n";
        $table .= sprintf("Today: Prod: %.1f kWh - Cons: %.1f kWh\nTomorrow: Prod: %.1f kWh - Cons: %.1f kWh\n", $prod_today, $cons_today, $prod_tomorrow, $cons_tomorrow);


        $fp = fopen("/dev/shm/EnergyManager_plan.txt", "w");
        fwrite($fp, $table);
        fclose($fp);
        return $table;


    }

    /**
     * Get array with planning information
     * 
     * @param   string $date Datesting in format 'Y-m-d H'
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
    public function run()
    {
        $dt = new DateTime();
        $hour = $dt->format(DATE_H); //current hour

        $this->plan(); //Refresh

        if ($this->planing_status)
            return false;

        //Battery

        if (isset($this->battery_restrictions[$hour])) {
            $this->bat_obj->setMode($this->battery_restrictions[$hour], $this->battery_active_flow[$hour]??0);
        }


        //BEV
        if ($this->bev[$hour] > 0) {
            $this->bev_obj->charge($this->bev[$hour], 2 / 60);
        }
        return true;

    }


}
