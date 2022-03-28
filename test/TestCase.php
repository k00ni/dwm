<?php

declare(strict_types=1);

namespace DWM\Test;

use DWM\DWMConfig;
use PHPUnit\Framework\TestCase as FrameworkTestCase;

class TestCase extends FrameworkTestCase
{
    protected string $rootDir;

    public function setUp(): void
    {
        $this->rootDir = __DIR__.'/..';
    }

    protected function generateDWMConfigMock(): DWMConfig
    {
        $dwmConfig = $this->createMock(DWMConfig::class);

        $dwmConfig->method('getPrefix')->willReturn('test://');

        $dwmConfig->method('getDefaultNamespacePrefixForKnowledgeBasedOnDatabaseTables')
            ->willReturn('test');

        $dwmConfig->method('getDefaultNamespaceUriForKnowledgeBasedOnDatabaseTables')
            ->willReturn('test://');

        $dwmConfig->method('getGenerateKnowledgeBasedOnDatabaseTablesAccessData')
            ->willReturn([
                'dbname' => 'dwm_test',
                'user' => 'root',
                'password' => 'Pass123',
                'host' => 'db',
                'driver' => 'pdo_mysql',
            ]);

        return $dwmConfig;
    }
}
