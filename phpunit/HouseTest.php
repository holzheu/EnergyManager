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

        $pv = new \EnergyManager\PV\PVFile();
        $pv->setTimeObj($time);

        $bat = new \EnergyManager\Battery\BatteryDummy([
            'kwh' => 10,
            'soc' => 90,
            'charge_power' => 1.5
        ]);

        $house = new \EnergyManager\House\HouseConstant(['kwh_per_day' => 10]);
        $house->setTimeObj($time);
        $em = new \EnergyManager\EnergyManager($pv, $bat, $price, $house);
        $em->setTimeObj($time);
        $em->plan(); ## calls house->plan()

        $res = $house->getPlan();
        $this->assertEqualsWithDelta(0.416666, $res[1719882000], 0.0001);

    }
}