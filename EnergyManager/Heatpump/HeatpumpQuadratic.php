<?php

/**
 * Simple Heatpump with quadratic equation for
 * electic power calculation
 */

namespace EnergyManager\Heatpump;
class HeatpumpQuadratic extends Heatpump
{
    public function __construct($settings, \EnergyManager\Temp\Temp $temp_obj)
    {
        $this->defaults = [
            "heating_limit" => 15,
            "indoor_temp" => 20,
            "lin_coef" => null,
            "quad_coef" => null,
            "refresh" => 3600 * 3
        ];
        $this->setSettings($settings);
        $this->temp_obj = $temp_obj;

    }

    public function refresh()
    {
        return $this->temp_obj->refresh();
    }

    public function setMode(string $mode)
    {

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
        $prices = $price_obj->get_ordered_price_slice($this->time(), $this->time() + 24 * 3600, true);
        $disabled = 0;
        foreach ($prices as $hour => $price) {
            if (($price - $mean_price) < 30)
                break;
            $this->mode[$hour] = 'disabled';
            $disabled++;
        }

        $prices = $price_obj->get_ordered_price_slice($this->time(), $this->time() + 24 * 3600, false);
        $enhanced = 0;
        foreach ($prices as $hour => $price) {
            if (($price - $mean_price) > -30 && $price > 0)
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
            if (($this->mode[$hour] ?? '') == 'disabled')
                $this->plan[$hour] = 0;
            if (($this->mode[$hour] ?? '') == 'enhanced')
                $this->plan[$hour] *= 2;
        }
        return true;
    }

    protected function getKw($temp)
    {
        if ($temp > $this->settings['heating_limit'])
            return 0;

        $t_diff = $this->settings['indoor_temp'] - $temp;
        return $this->settings['lin_coef'] * $t_diff +
            $this->settings['quad_coef'] * $t_diff * $t_diff; //Numbers from Auswertung_Heizung.R 

    }

    /**
     * getPriceCorrection
     * 
     * Calculates a price correction factor for 
     * the changing COP of the HP
     * 
     * @param mixed $temp
     * @return float
     */
    protected function getPriceCorrection($temp): float
    {
        if ($temp > $this->settings['heating_limit'])
            return 1;
        $t_diff = $this->settings['indoor_temp'] - $temp;
        return 1 + $this->settings['quad_coef'] / $this->settings['lin_coef'] * $t_diff;
    }

    protected function orderPrices(array $temps, array $prices, $desc = false)
    {
        $prices_eff = [];
        foreach ($prices as $hour => $price) {
            $prices_eff[$hour] = $price * $this->getPriceCorrection($temps[$hour]);
        }

        if ($desc)
            arsort($prices_eff);
        else
            asort($prices_eff);
        return $prices_eff;
    }

}