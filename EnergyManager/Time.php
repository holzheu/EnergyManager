<?php

namespace EnergyManager;

class Time{
    private ?float $time=null;

    public function set($time=null){
        $this->time=$time;
    }

    public function get(){
        if($this->time===null) return time();
        return $this->time;
    }

}