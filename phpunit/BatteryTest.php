<?php

use function PHPUnit\Framework\assertIsArray;

class BatteryTest extends \PHPUnit\Framework\TestCase {
    public function testBattery(){
        require_once __DIR__ . "/../EnergyManager/EnergyManager.php";
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