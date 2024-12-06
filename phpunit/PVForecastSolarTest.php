<?php

use function PHPUnit\Framework\assertArrayHasKey;

class PvForecastSolarTest extends \PHPUnit\Framework\TestCase
{
    public function testPVForecastSolar()
    {
        $pv = new \EnergyManager\PV\PvForecastSolar([
            'lon'=>'10.232',
            'lat'=>'49.342',
            'dec'=>[50, 50],
            'az'=>[-50,130],
            'kwp'=>[5,10]
        ]);
        $pv->refresh();
        $result = $pv->getProduction();
        $hour=floor(time()/3600/24)*3600*24+12*3600;
        assertArrayHasKey($hour,$result);
        assertArrayHasKey($hour+24*3600,$result);
     }
}