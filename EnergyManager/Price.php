<?php
/**
 * Price classes
 * 
 */

require_once(dirname(__FILE__) ."/Device.php");

/**
 * Abstract price class
 */
abstract class Price extends Device {
    protected $price;
    protected $min_today;
    protected $max_today;
    protected $min_tomorrow;
    protected $max_tomorrow;

    /**
     * Returns a ordered price slice of the price Array
     * used for planing...
     * 
     * @param mixed $start Start hour in the format "Y-m-d H"
     * @param mixed $end End hour (not included)
     * @param mixed $desc Order - set true for descend order
     * @return array 
     */
    public function get_ordered_price_slice($start, $end = null, $desc = false)
    {
        $hours = array_keys($this->price);
        $start = array_search($start, $hours);
        if ($start === false)
            return [];
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

    public function getMin_today(){
        return $this->min_today;
    }

    public function getMax_today(){
        return $this->max_today;
    }

    public function getMin_tomorrow(){
        return $this->min_tomorrow;
    }

    public function getMax_tomorrow(){
        return $this->max_tomorrow;
    }

    public function getPrice(){
        return $this->price;
    }

    public function getStatus(){
        $out= "Price Status\n";
        $out.= sprintf("Min Today: %.1f\n",$this->min_today);
        $out.= sprintf("Max Today: %.1f\n",$this->max_today);
        $out.= sprintf("Min Tomorrow: %.1f\n",$this->min_tomorrow);
        $out.= sprintf("Max Tomorrow: %.1f\n",$this->max_tomorrow);


        return $out;
    }
    
}


/**
 * Implementation for Awattar
 */
class Price_Awattar extends Price {

    public function refresh(){
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
        $this->min_today = 3000;
        $this->min_tomorrow = 3000;
        $this->max_today = -500;
        $this->max_tomorrow = -500;
        $today = $dt->format("Y-m-d");
        $dt->modify("+1 day");
        $tomorrow = $dt->format("Y-m-d");
        foreach ($json->data as $x) {
            $dt->setTimestamp($x->start_timestamp / 1000);
            $this->price[$dt->format(DATE_H)] = $x->marketprice;
            if ($dt->format('Y-m-d') == $today){
                if($x->marketprice < $this->min_today) $this->min_today = $x->marketprice;
                if($x->marketprice > $this->max_today) $this->max_today = $x->marketprice;
            } 
            if ($dt->format('Y-m-d') == $tomorrow){
                if($x->marketprice < $this->min_tomorrow) $this->min_tomorrow = $x->marketprice;
                if($x->marketprice > $this->max_tomorrow) $this->max_tomorrow = $x->marketprice;                
            } 

        }
        return true;



    }

}