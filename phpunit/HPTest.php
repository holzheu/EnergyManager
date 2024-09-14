<?php

class HPTest extends \PHPUnit\Framework\TestCase
{
    public function testHP()
    {
        $time = new \EnergyManager\Time();
        $dt = new \DateTime("2024-07-01 10:00");
        $time->set($dt->getTimestamp());

        $temp = new \EnergyManager\Temp\TempFile();

        $temp->setTimeObj($time);
        $hp = new \EnergyManager\Heatpump\HeatpumpQuadratic([
            "lin_coef" => 0.017678,
            "quad_coef" => 0.002755
        ], $temp);
        $hp->setTimeObj($time);
        
        require __DIR__.'/../EnergyManager/secrets.php';
        $hp = new \EnergyManager\Heatpump\HeatpumpDimplexDaikin([
            "lin_coef" => 0.017678,
            "quad_coef" => 0.002755,
            'ip'=>DimplexIP,
            'daikin'=>DaikinIPs
        ], $temp);
        $hp->setTimeObj($time);

        $hp->refresh();

        $price = new \EnergyManager\Price\PriceFile();
        $price->setTimeObj($time);
        $price->refresh();

        $pv = new \EnergyManager\PV\PVFile();
        $pv->setTimeObj($time);
        $pv->refresh();

        $bat = new \EnergyManager\Battery\BatteryDummy([
            'kwh' => 10,
            'soc' => 50
        ]);

        $house = new \EnergyManager\House\HouseConstant(['kwh_per_day' => 10]);
        $house->setTimeObj($time);

        $manager = new \EnergyManager\EnergyManager($pv, $bat, $price, $house);
        $manager->setTimeObj($time);

        $manager->refresh();

        $hp->plan($manager->getFreeProduction(), $price);
        $hp->setTimeObj($time);

        $res = $hp->getPlan();
        $this->assertArrayHasKey(1719831600, $res);
        $this->assertContains(0, $res);
        $this->assertEqualsWithDelta(0.1883425915, $res[1719882000], 0.0001);

    }
}