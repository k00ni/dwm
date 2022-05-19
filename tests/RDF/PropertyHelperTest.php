<?php

declare(strict_types=1);

namespace DWM\Tests\RDF;

use DWM\RDF\NamespaceHelper;
use DWM\RDF\PropertyHelper\PropertyHelper;
use DWM\RDF\RDFGraph;
use DWM\Test\TestCase;

class PropertyHelperTest extends TestCase
{
    private function getSubjectUnderTest(RDFGraph $graph): PropertyHelper
    {
        $sut = new PropertyHelper([
            'http://www.w3.org/2000/01/rdf-schema#label',
            'http://some-label/',
        ]);
        $sut->init($graph);

        return $sut;
    }

    private function getGraph(): RDFGraph
    {
        $graph = new RDFGraph(new NamespaceHelper());

        $graph->initialize([
            [
                '@id' => 'http://foo',
                'http://prop1/' => 'prop1 value',
                'http://some-label/' => [
                    [
                        '@value' => 'some label de',
                        '@language' => 'de',
                    ],
                    [
                        '@value' => 'some label en',
                        '@language' => 'en',
                    ],
                ],
                'http://www.w3.org/2000/01/rdf-schema#label' => [
                    [
                        '@value' => 'rdfs label de',
                        '@language' => 'de',
                    ],
                    [
                        '@value' => 'rdfs label en',
                        '@language' => 'en',
                    ],
                ],
            ],
            [
                '@id' => 'http://foo2',
                'http://prop1/' => 'prop1 value',
                'http://some-label/' => [
                    [
                        '@value' => 'some label de',
                        '@language' => 'de',
                    ],
                    [
                        '@value' => 'some label en',
                        '@language' => 'en',
                    ],
                ],
            ],
        ]);

        return $graph;
    }

    public function testUsage(): void
    {
        $sut = $this->getSubjectUnderTest($this->getGraph());

        self::assertEquals('rdfs label de', $sut->getValue('http://foo', 'de'));
        self::assertEquals('rdfs label en', $sut->getValue('http://foo', 'en'));

        self::assertEquals('rdfs label de', $sut->getValue('http://foo'));

        self::assertNull($sut->getValue('http://foo', 'invalid'));
    }

    public function testRankingOfPreferredPropertyUris(): void
    {
        $sut = $this->getSubjectUnderTest($this->getGraph());

        self::assertEquals('some label de', $sut->getValue('http://foo2', 'de'));
        self::assertEquals('some label en', $sut->getValue('http://foo2', 'en'));

        self::assertEquals('some label de', $sut->getValue('http://foo2'));

        self::assertNull($sut->getValue('http://foo2', 'invalid'));
    }
}
