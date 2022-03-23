<?php

declare(strict_types=1);

namespace DWM\Test;

use PHPUnit\Framework\TestCase as FrameworkTestCase;

class TestCase extends FrameworkTestCase
{
    protected string $rootDir;

    public function setUp(): void
    {
        $this->rootDir = __DIR__.'/..';
    }
}
