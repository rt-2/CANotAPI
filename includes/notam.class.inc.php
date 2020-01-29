<?php

class Notam
{
    public $ident = null;
    public $airport = null;
    public $time_from = null;
    public $time_to = null;
    public $time_human = null;
    public $text = null;


    public function __construct($configArray) {
    

        if(isset($configArray['ident'])) $this->ident = $configArray['ident'];
        if(isset($configArray['airport'])) $this->airport = $configArray['airport'];
        if(isset($configArray['time_from'])) $this->time_from = $configArray['time_from'];
        if(isset($configArray['time_to'])) $this->time_to = $configArray['time_to'];
        if(isset($configArray['text'])) trim($this->text = trim($configArray['text']));
        if(isset($configArray['time_human'])) $this->time_human = trim($configArray['time_human']);
        if(strlen($this->time_human) > 0) $this->text .= ' '.$this->time_human;
        
        

        $this->text = str_replace ( ',' , '' , $this->text);
        $this->text = str_replace ( '.' , '' , $this->text);
        $this->text = str_replace ( PHP_EOL  , '' , $this->text);


    }
    public function GetIdent()
    {
        return $this->ident;
    }
    public function GetAirport()
    {
        return $this->airport;
    }
    public function GetTimeFrom()
    {
        return $this->time_from;
    }
    public function GetTimeTo()
    {
        return $this->time_to;
    }
    public function GetTimeHuman()
    {
        return $this->time_human;
    }
    public function GetText()
    {
        return $this->text;
    }
}