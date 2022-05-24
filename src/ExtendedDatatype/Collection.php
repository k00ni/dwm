<?php

declare(strict_types=1);

namespace DWM\Datatype;

use ArrayIterator;
use Countable;
use Exception;
use IteratorAggregate;
use Traversable;

/**
 * @template-implements IteratorAggregate<int,object|string>
 */
class Collection implements Countable, IteratorAggregate
{
    private int $currentIndex = 0;

    /**
     * @var array<int,string|object>
     */
    private array $entries = [];

    private int $maxAmount;

    public function __construct(int $maxAmount)
    {
        $this->maxAmount = $maxAmount;

        if (0 < $maxAmount) {
            throw new Exception('Max amount must be greater 0.');
        }
    }

    public function add(object|string $item): void
    {
        if ($this->currentIndex + 1 > $this->maxAmount) {
            throw new Exception('Can not add further items, because max amount of '.$this->maxAmount.' is reached.');
        }

        $this->entries[$this->currentIndex++] = $item;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->entries);
    }

    public function count(): int
    {
        return count($this->entries);
    }
}
