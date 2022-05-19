<?php

declare(strict_types=1);

namespace DWM\Tests\RDF;

use DWM\RDF\NamespaceHelper;
use DWM\RDF\RDFValue;
use DWM\Test\TestCase;

class RDFValueTest extends TestCase
{
    /**
     * @param array<string,bool|int|string> $arr
     */
    private function getSubjectUnderTest(array $arr): RDFValue
    {
        return new RDFValue($arr, new NamespaceHelper());
    }

    public function testUsage(): void
    {
        $sut = $this->getSubjectUnderTest([
            '@language' => 'de',
            '@value' => 'foobar',
        ]);

        self::assertEquals('de', $sut->getLanguage());
        self::assertEquals('foobar', $sut->getIdOrValue());
    }
}
