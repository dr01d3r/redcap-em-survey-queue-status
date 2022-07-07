<?php

namespace ORCA\SurveyQueueStatus;

trait REDCapUtils {

    private $timers = [];

    public function preout($content) {
        if (is_array($content) || is_object($content)) {
            echo "<pre>" . print_r($content, true) . "</pre>";
        } else {
            echo "<pre>$content</pre>";
        }
    }

    public function addTime($key = null) {
        if ($key == null) {
            $key = "STEP " . count($this->timers);
        }
        $this->timers[] = [
            "label" => $key,
            "value" => microtime(true)
        ];
    }

    public function outputTimerInfo($showAll = false, $return = false) {
        $initTime = null;
        $preTime = null;
        $curTime = null;
        $output = [];
        foreach ($this->timers as $index => $timeInfo) {
            $curTime = $timeInfo;
            if ($preTime == null) {
                $initTime = $timeInfo;
            } else {
                $calcTime = round($curTime["value"] - $preTime["value"], 4);
                if ($showAll) {
                    if ($return === true) {
                        $output[] = "{$timeInfo["label"]}: {$calcTime}";
                    } else {
                        echo "<p><i>{$timeInfo["label"]}: {$calcTime}</i></p>";
                    }
                }
            }
            $preTime = $curTime;
        }
        $calcTime = round($curTime["value"] - $initTime["value"], 4);
        if ($return === true) {
            $output[] = "Total Processing Time: {$calcTime} seconds";
            return $output;
        } else {
            echo "<p><i>Total Processing Time: {$calcTime} seconds</i></p>";
        }
    }
}
