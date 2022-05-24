<?php

namespace DWM;

class Simulator
{
    private array $environment = [
        'start_time' => 0,
    ];

    private array $events = [
        'on_time_change' => []
    ];

    private function callEventListeners(string $event, array $params = []): void
    {
        foreach ($this->events[$event] as $callable) {
            $callable($params);
        }
    }

    /**
     * Task:
     * - obj1 goes from x=0 to x=10
     * - obj1 needs gas to move (+ refill)
     */
    public function runTask2(): void
    {
        /**
         * setup simulation ----------------------------------------------------------------
         */
        $taskInformation = [
            'time_step' => 1,
            'max_time' => 10,
            'obj1' => [
                'x' => 0,
                'gas_tank_max_capacity' => 5,
                'gas_tank_capacity' => 5,
                'gas_consumption_per_step' => 1,
            ]
        ];

        // move function
        $this->events['on_time_change'][0] = function() use (&$taskInformation) {
            // as long as we have energy to spare...
            if (0 < $taskInformation['obj1']['gas_tank_capacity']) {
                $taskInformation['obj1']['x'] += 1;
                $taskInformation['obj1']['gas_tank_capacity'] -= $taskInformation['obj1']['gas_consumption_per_step'];
            } else {
                $this->callEventListeners('on_refill_gas_tank');
            }
        };

        // report
        $this->events['on_time_change'][1] = function(array $params) use(&$taskInformation) {
            echo PHP_EOL.'--------------------------------------';
            echo PHP_EOL;
            echo 't = '.$params['t'] .' // obj1.x = '.$taskInformation['obj1']['x'].' (capacity='.$taskInformation['obj1']['gas_tank_capacity'].')';
        };

        // refill gas tank
        $this->events['on_refill_gas_tank'][0] = function(array $params) use(&$taskInformation) {
            $taskInformation['obj1']['gas_tank_capacity'] = $taskInformation['obj1']['gas_tank_max_capacity'];
            echo ' --- refill';
        };

        /**
         * start simulation ----------------------------------------------------------------
         */
        for ($t=$this->environment['start_time']; $t < $taskInformation['max_time']; $t += $taskInformation['time_step']) {
            $this->callEventListeners('on_time_change', ['t' => $t]);
        }
    }

    /**
     * Task: move obj1 from x=0 to x=10
     */
    public function runTask1(): void
    {
        /**
         * setup simulation ----------------------------------------------------------------
         */
        $taskInformation = [
            'time_step' => 1,
            'max_time' => 10,
            'obj1' => [
                'x' => 0,
            ]
        ];

        // move function
        $this->events['on_time_change'][0] = function() use (&$taskInformation) {
            $taskInformation['obj1']['x'] += 1; // = speed
        };

        // report
        $this->events['on_time_change'][1] = function(array $params) use(&$taskInformation) {
            echo PHP_EOL.'--------------------------------------';
            echo PHP_EOL;
            echo 't = '.$params['t'] .' // obj1.x = '.$taskInformation['obj1']['x'];
        };

        /**
         * start simulation ----------------------------------------------------------------
         */
        for ($t=$this->environment['start_time']; $t < $taskInformation['max_time']; $t += $taskInformation['time_step']) {
            $this->callEventListeners('on_time_change', ['t' => $t]);
        }
    }
}
