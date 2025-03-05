<?php
namespace FastApiPHP;


class Duration
{

    public $APP;
    public $durations = [];


    public function __construct($APP=null)
    {
        $this->APP = $APP;
        $this->durations = [];
    }


    public function start($name=null)
    {
        if (!$name) return;
        $timeStart = round(microtime(true) * 1000);
        $this->durations[$name] = ["start"=>$timeStart, "finish"=>null, "duration"=>null];
    }

    public function finish($name=null)
    {
        if (!$name) return;
        if (!isset($this->durations[$name])) return;

        $timeStart = $this->durations[$name]["start"];
        $timeFinish = round(microtime(true) * 1000);
        $timeDuration  = round($timeFinish - $timeStart, 3);
        $this->durations[$name]["finish"] = $timeFinish;
        $this->durations[$name]["duration"] = $timeDuration;
    }

    public function get()
    {
        if (count($this->durations) == 0) return null;
        if (defined('APP_DISABLE_CALC_DURATION') && APP_DISABLE_CALC_DURATION===true) return null;
        return $this->durations;
    }


}
