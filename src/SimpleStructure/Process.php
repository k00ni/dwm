<?php

declare(strict_types=1);

namespace DWM\SimpleStructure;

use Exception;

/**
 * A process consists of a finite amount of ordered steps.
 *
 * @see https://github.com/k00ni/dwm#Process
 */
abstract class Process
{
    private mixed $result;

    private FirstInFirstOutList $steps;

    public function __construct()
    {
        $this->steps = new FirstInFirstOutList();
    }

    protected function addStep(string $functionName): void
    {
        $this->steps->addElementToList($functionName);
    }

    public function doSteps(): self
    {
        if (0 == $this->steps->getCount()) {
            throw new Exception('No steps added before. Nothing to do.');
        }

        while ($data = $this->steps->getNextElementFromList()) {
            call_user_func([$this, $data]);
        }

        return $this;
    }

    protected function verifyThisInstance()
    {
        // TODO check compliance with a given TTL file?
    }

    protected function getResult(): mixed
    {
        return $this->result;
    }
}
