<?php

declare(strict_types=1);

namespace DWM\Tests\RDF;

use DWM\Process\GenerateDBSchemaBasedOnKnowledge;
use DWM\Test\DBTestCase;

class GenerateDBSchemaBasedOnKnowledgeTest extends DBTestCase
{
    public function testUsage(): void
    {
        // load test data
        $sqls = require $this->rootDir.'/test/data/sqls1.php';
        foreach ($sqls as $sql) {
            $this->connection->executeQuery($sql);
        }

        $dwmConfig = $this->generateDwmConfigMock();

        $sut = new GenerateDBSchemaBasedOnKnowledge($dwmConfig, false);
        $result = $sut->doSteps()->getResult();

        self::assertEquals($data['@graph'][0]['@id'], 'test:User');
        self::assertEquals($data['@graph'][1]['@id'], 'test:UserShape');

        self::assertCount(7, $data['@graph'][1]['sh:property']);
    }
}
