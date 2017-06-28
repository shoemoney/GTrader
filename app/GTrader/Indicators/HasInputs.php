<?php

namespace GTrader\Indicators;

use GTrader\Series;
use GTrader\Indicator;

abstract class HasInputs extends Indicator
{

    public function getInputs()
    {
        $inputs = [];
        if (!$this->hasinputs()) {
            return $inputs;
        }
        //error_log('HasInputs::getInputs() params: '.json_encode($this->getParam('indicator')));
        foreach ($this->getParam('indicator') as $k => $v) {
            if ('input_' === substr($k, 0, 6)) {
                if (!is_string($v)) {
                    $v = json_encode($v);
                    //error_log('HasInputs::getInputs() val is not a str: '.$v);
                    //print_r(debug_backtrace()); exit;
                }
                $inputs[$k] = $v;
            }
        }
        return $inputs;
    }

    public function getInput(string $name = null)
    {
        if (!$this->hasinputs()) {
            return null;
        }
        if (!is_null($name)) {
            $name = $this->getParam('indicator.'.$name);
            if (!is_string($name)) {
                $name = json_encode($name);
            }
            return $name;
        }
        $inputs = $this->getInputs();
        return array_shift($inputs);
    }

    public function hasInputs()
    {
        return true;
    }

    public function inputFrom($signatures)
    {
        if (!$this->hasInputs()) {
            return false;
        }
        if (!is_array($signatures)) {
            $signatures = [$signatures];
        }
        $inputs = $this->getInputs();
        if (count(array_intersect($inputs, $signatures))) {
            return true;
        }
        $params = ['display' => ['visible' => false]];
        foreach ($inputs as $input) {
            if (!($input_ind = $this->getOwner()->getOrAddIndicator($input, [], $params))) {
                continue;
            }
            if (!$input_ind->hasInputs()) {
                continue;
            }
            if ($input_ind->inputFrom($signatures)) {
                return true;
            }
        }
        return false;
    }

    public function inputFromIndicator()
    {
        $available = $this->getOwner()->getIndicatorsAvailable();
        foreach ($this->getInputs() as $input) {
            $class = Indicator::getClassFromSignature($input);
            if (array_key_exists($class, $available)) {
                return true;
            }
        }
        return false;
    }

    public function getOrAddInputIndicators()
    {
        if (!($owner = $this->getOwner())) {
            return null;
        }
        $params = ['display' => ['visible' => false]];
        $inputs = $this->getInputs();
        $inds = [];
        foreach ($inputs as $input) {
            if (!($indicator = $owner->getOrAddIndicator($input, [], $params))) {
                //error_log(get_class($this).'::getOrAddInputIndicators() could not find indicator '.
                //    $input.' for '.get_class($owner));
                return null;
            }
            $inds[] = $indicator;
        }
        return count($inds) ? $inds : false;
    }


    public function createDependencies()
    {
        if (!$this->inputFromIndicator()) {
            return $this;
        }
        if (!$this->getOrAddInputIndicators()) {
            //error_log('Could not getOrAdd input indicators for '.get_class($this));
        }
        return $this;
    }


    public function runDependencies(bool $force_rerun = false)
    {
        $inputs = $this->getInputs();
        //error_log('HasInputs::runDependencies() inputs: '.json_encode($inputs));
        if (in_array('volume', $inputs)) {
            $this->setParam('display.y_axis_pos', 'right');
        }
        else if (!$this->inputFromIndicator() &&
            count(array_intersect(['open', 'high', 'low', 'close'], $inputs))) {
            $this->setParam('display.y_axis_pos', 'left');
            return true;
        }
        if (! $inds = $this->getOrAddInputIndicators()) {
            //error_log('HasInputs::runDependencies() could not getOrAdd input indicators for '.get_class($this));
            return $this;
        }
        $count_left = 0;
        foreach ($inds as $ind) {
            if ('left' === $ind->getParam('display.y_axis_pos')) {
                $count_left++;
            }
            $ind->addRef($this->getSignature());
            $ind->checkAndRun($force_rerun);
        }
        $this->setParam('display.y_axis_pos', ($count_left === count($inds)) ? 'left' : 'right');
        return $this;
    }


    public function extract(Series $candles, string $index_type = 'sequential')
    {
        $out = [];
        foreach ($this->getInputs() as $input) {
            $out[$input] = $candles->extract($input, $index_type);
        }
        return $out;
    }

}
