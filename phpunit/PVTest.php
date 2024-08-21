<?php

use function PHPUnit\Framework\assertEqualsWithDelta;

class PVTest extends \PHPUnit\Framework\TestCase
{
    public function testPV()
    {
        $pv = new \EnergyManager\PV\PvDummy();
        $pv->refresh();
        $result = $pv->getProduction();
        $this->assertIsArray($result);
        $this->assertContains(2.54, $result);


        $time = new \EnergyManager\Time();
        $dt = new \DateTime("2024-07-01 10:00");
        $time->set($dt->getTimestamp());

        $pv = new \EnergyManager\PV\PVFile();
        $pv->setTimeObj($time);
        $pv->refresh();

        $result = $pv->getProduction();
        assertEqualsWithDelta(1.1205,$result[1719892800],0.0001);


     }
}