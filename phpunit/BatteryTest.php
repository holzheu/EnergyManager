<?php

class BatteryTest extends \PHPUnit\Framework\TestCase {
    public function testBattery(){
        $bat = new \EnergyManager\Battery\BatteryDummy([
            'kwh' => 10,
            'soc' => 50
        ]);
        
        $bat->refresh();
        $result=$bat->getCapacity();
        $this->assertEquals(10, $result);

        $result=$bat->getSOC();
        $this->assertEquals(50, $result);

    }
}  