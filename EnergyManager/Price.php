<?php
/**
 * Price classes
 * 
 */

require_once (dirname(__FILE__) . "/Device.php");

/**
 * Abstract price class
 */
abstract class Price extends Device
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
        $start = time();
        $prices = $this->get_ordered_price_slice($start, $start + 3600 * $hours);
        return array_values($prices)[0];
    }

    public function getMax(int $hours): float
    {
        $start = time();
        $prices = $this->get_ordered_price_slice($start, $start + 3600 * $hours, true);
        return array_values($prices)[0];
    }

    public function getPrice(): array
    {
        return $this->price;
    }

}


/**
 * Implementation for Awattar
 */
class Price_Awattar extends Price
{

    public function refresh()
    {
        $dt = new DateTime();
        if (($dt->getTimestamp() - $this->update) < 3600)
            return true;

        $this->update = $dt->getTimestamp();
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
            return false;
        }
        $json = json_decode($json);
        $this->price = [];
        foreach ($json->data as $x) {
            $this->price[$x->start_timestamp / 1000] = $x->marketprice;
        }
        return true;



    }

}