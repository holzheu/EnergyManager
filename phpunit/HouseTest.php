<?php

class HouseTest extends \PHPUnit\Framework\TestCase
{
    public function testHouse()
    {
        $time = new \EnergyManager\Time();
        $dt = new \DateTime("2024-07-01 10:00");
        $time->set($dt->getTimestamp());

        $price = new \EnergyManager\Price\PriceFile();
        $price->setTimeObj($time);
        $price->refresh();

        $pv = new \EnergyManager\PV\PVFile();
        $pv->setTimeObj($time);
        $pv->refresh();


        $house = new \EnergyManager\House\HouseConstant(['kwh_per_day' => 10]);
        $house->setTimeObj($time);
        $house->plan($pv->getProduction(), $price);

        $res = $house->getPlan();
        $this->assertEqualsWithDelta(0.416666, $res[1719882000], 0.0001);

    }
}