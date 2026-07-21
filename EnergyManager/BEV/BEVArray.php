<?php
namespace EnergyManager\BEV;

class BEVArray {
    private array $bev=[];

    public function addBEV(BEV $bev){
        $this->bev[]=$bev;
    }

    public function getBEV($nr=0):BEV{
        return $this->bev[$nr];
    }

    public function getCount():int{
        return count($this->bev);
    }
}
