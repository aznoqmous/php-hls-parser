<?php

namespace App;

class Resolution {

    public $width;
    public $height;

    public $res;

    public function __construct($width, $height)
    {
        $this->width = $width;
        $this->height = $height;

        $this->res = $height;
    }

    public function getLabel(){
        return "{$this->width}x{$this->height}";
    }

}