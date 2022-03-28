<?php

declare(strict_types=1);

namespace DWM\Tests\RDF;

use DWM\Process\GenerateKnowledgeBasedOnDBTables;
use DWM\Test\DBTestCase;

class GenerateKnowledgeBasedOnDBTablesTest extends DBTestCase
{
    public function testUsage(): void
    {
        // load test data
        $sqls = require $this->rootDir.'/test/data/sqls1.php';
        foreach ($sqls as $sql) {
            $this->connection->executeQuery($sql);
        }

        $dwmConfig = $this->generateDwmConfigMock();

        $sut = new GenerateKnowledgeBasedOnDBTables($dwmConfig, false);
        $data = $sut->doSteps()->getResult();

        var_dump($data);
    }
}
