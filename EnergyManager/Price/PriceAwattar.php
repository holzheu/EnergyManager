<?php

/**
 * Implementation for Awattar
 */

namespace EnergyManager\Price;
class PriceAwattar extends Price
{

    public function refresh()
    {
        $dt = new \DateTime();
        if (($dt->getTimestamp() - $this->update) < 3600)
            return true;

        $this->update = $dt->getTimestamp();
        //fwrite(STDOUT, date('Y-m-d H:i:s') . ' ' . "EnergyManager: refresh price\n");

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