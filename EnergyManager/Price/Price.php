<?php
/**
 * Abstract price class
 */

namespace EnergyManager\Price;

abstract class Price extends \EnergyManager\Device
{
    protected array $price;

    /**
     * Returns a ordered price slice of the price Array
     * used for planing...
     * 
     * @param float $start Start hour as unix timestamp "
     * @param float $end End hour (not included)
     * @param bool $desc Order - set true for descend order
     * @return array 
     */
    public function get_ordered_price_slice($start, $end = null, $desc = false)
    {
        $start = floor($start / 3600) * 3600;
        if (!is_null($end))
            $end = floor($end / 3600) * 3600;
        $hours = array_keys($this->price);
        $start = array_search($start, $hours);
        if ($start === false)
            return []; //Index not found
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

    public function getMin(int $hours): float
    {
        $start = $this->time();
        $prices = $this->get_ordered_price_slice($start, $start + 3600 * $hours);
        return array_values($prices)[0];
    }

    public function getMax(int $hours): float
    {
        $start = $this->time();
        $prices = $this->get_ordered_price_slice($start, $start + 3600 * $hours, true);
        return array_values($prices)[0];
    }

    public function getMean(int $hours): float
    {
        $hour = $this->full_hour($this->time());
        $mean = 0;
        $count = 0;
        while (isset($this->price[$hour])) {
            $mean += $this->price[$hour];
            $hour += 3600;
            $count++;
            if ($count == $hours)
                break;
        }
        if (!$count)
            return NAN;
        return $mean / $count;

    }

    public function getPrice(): array
    {
        return $this->price;
    }

}


