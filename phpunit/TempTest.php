<?php

use function PHPUnit\Framework\assertIsArray;

class TempTest extends \PHPUnit\Framework\TestCase {
    public function testTemp(){
        require_once __DIR__ . "/../EnergyManager/EnergyManager.php";
        $temp = new \EnergyManager\Temp\TempOpenMeteo([
            "latitude" => 50.0333,
            "longitude" => 11.5667
        ]);
        
        $temp->refresh();
        $result=$temp->getDaily();
        $this->assertIsArray($result);

        $temp->refresh();
        $result=$temp->getHourly();
        $this->assertIsArray($result);       
    }
}  