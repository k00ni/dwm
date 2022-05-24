<?php

declare(strict_types=1);

namespace DWM\Tests\SQL;

use DWM\Simulator;
use DWM\SQL\MySQLColumnIndex;
use DWM\Test\DBTestCase;

class SimulatorTest extends DBTestCase
{
    public function test()
    {
        $sut = new Simulator();

        $sut->runTask2();
    }
}
