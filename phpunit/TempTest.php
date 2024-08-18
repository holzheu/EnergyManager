<?php

class TempTest extends \PHPUnit\Framework\TestCase
{
    public function testTemp()
    {
        $temp = new \EnergyManager\Temp\TempOpenMeteo([
            "latitude" => 50.0333,
            "longitude" => 11.5667
        ]);

        $temp->refresh();
        $result = $temp->getDaily();
        $this->assertIsArray($result);

        $temp->refresh();
        $result = $temp->getHourly();
        $this->assertIsArray($result);
    }
}