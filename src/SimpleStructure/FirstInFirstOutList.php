<?php

declare(strict_types=1);

namespace DWM\SimpleStructure;

/**
 * @see https://en.wikipedia.org/wiki/FIFO_(computing_and_electronics)
 */
final class FirstInFirstOutList
{
    private array $elements = [];
    private int $counter = 0;

    public function addElementToList(mixed $element): void
    {
        $this->elements[$this->counter] = $element;
        ++$this->counter;
    }

    public function getNextElementFromList(): mixed
    {
        return array_shift($this->elements);
    }

    public function getCount(): int
    {
        return count($this->elements);
    }
}
