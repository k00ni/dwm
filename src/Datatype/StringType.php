<?php

declare(strict_types=1);

namespace DWM\Datatype;

use Exception;

/**
 * Represents a string value with a given min- and max length.
 */
class StringType
{
    private string $value;

    public function __construct(string $value, int $minLength, int $maxLength)
    {
        if ($minLength <= strlen($value) && strlen($value) <= $maxLength) {
            $this->value = $value;
        } else {
            throw new Exception('Length of given string is not between '.$minLength.' and '.$maxLength);
        }
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
