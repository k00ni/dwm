<?php

declare(strict_types=1);

namespace DWM\Tests\RDF;

use DWM\RDF\NamespaceHelper;
use DWM\RDF\PropertyBasedHierarchyBuilder;
use DWM\RDF\RDFGraph;
use DWM\Test\TestCase;

class PropertyBasedHierarchyBuilderTest extends TestCase
{
    private function getSubjectUnderTest(RDFGraph $graph): PropertyBasedHierarchyBuilder
    {
        $sut = new PropertyBasedHierarchyBuilder();
        $sut->init($graph);

        return $sut;
    }

    public function testBuildNested1(): void
    {
        $graph = new RDFGraph(new NamespaceHelper());

        /*
         * hierarchy looks like:
         *
         *      A                       <--- root
         *       `--- B
         *       |     `--- C
         *       |
         *       `--- D
         *             `--- E
         */
        $graph->initialize([
            [
                '@id' => 'http://class/C',
                'http://www.w3.org/2000/01/rdf-schema#subClassOf' => 'http://class/B',
            ],
            [
                '@id' => 'http://class/E',
                'http://www.w3.org/2000/01/rdf-schema#subClassOf' => 'http://class/D',
            ],
            [
                '@id' => 'http://class/D',
                'http://www.w3.org/2000/01/rdf-schema#subClassOf' => 'http://class/A',
            ],
            [
                '@id' => 'http://class/B',
                'http://www.w3.org/2000/01/rdf-schema#subClassOf' => 'http://class/A',
            ],
        ]);

        $sut = $this->getSubjectUnderTest($graph);

        $result = $sut->buildNested('rdfs:subClassOf');

        // check
        self::assertEquals(
            [
                'http://class/A' => [
                    'http://class/B' => [
                        'http://class/C' => [],
                    ],
                    'http://class/D' => [
                        'http://class/E' => [],
                    ],
                ],
            ],
            $result
        );
    }

    public function testBuildNested2(): void
    {
        $graph = new RDFGraph(new NamespaceHelper());

        /*
         * hierarchy looks like:
         *
         *      A                       <--- root
         *       `--- B
         *       |     `--- C
         *       |
         *       `--- D
         *             `--- E
         *      A2                      <--- root
         *       `--- B2
         *             `--- C2
         */
        $graph->initialize([
            [
                '@id' => 'http://class/C',
                'http://www.w3.org/2000/01/rdf-schema#subClassOf' => 'http://class/B',
            ],
            [
                '@id' => 'http://class/E',
                'http://www.w3.org/2000/01/rdf-schema#subClassOf' => 'http://class/D',
            ],
            [
                '@id' => 'http://class/C2',
                'http://www.w3.org/2000/01/rdf-schema#subClassOf' => 'http://class/B2',
            ],
            [
                '@id' => 'http://class/B2',
                'http://www.w3.org/2000/01/rdf-schema#subClassOf' => 'http://class/A2',
            ],
            [
                '@id' => 'http://class/D',
                'http://www.w3.org/2000/01/rdf-schema#subClassOf' => 'http://class/A',
            ],
            [
                '@id' => 'http://class/B',
                'http://www.w3.org/2000/01/rdf-schema#subClassOf' => 'http://class/A',
            ],
        ]);

        $sut = $this->getSubjectUnderTest($graph);

        $result = $sut->buildNested('rdfs:subClassOf');

        // check
        self::assertEquals(
            [
                'http://class/A' => [
                    'http://class/B' => [
                        'http://class/C' => [],
                    ],
                    'http://class/D' => [
                        'http://class/E' => [],
                    ],
                ],
                'http://class/A2' => [
                    'http://class/B2' => [
                        'http://class/C2' => [],
                    ],
                ],
            ],
            $result
        );
    }

    public function testBuildNested3(): void
    {
        $graph = new RDFGraph(new NamespaceHelper());

        /*
         * hierarchy looks like:
         *
         *      A                       <--- root
         *       `--- B
         *       |     `--- C
         *       |
         *       `--- D
         *             `--- D           <--- self reference, to be ignored
         */
        $graph->initialize([
            [
                '@id' => 'http://class/C',
                'http://www.w3.org/2000/01/rdf-schema#subClassOf' => 'http://class/B',
            ],
            [
                '@id' => 'http://class/D',
                'http://www.w3.org/2000/01/rdf-schema#subClassOf' => 'http://class/A',
            ],
            [
                '@id' => 'http://class/B',
                'http://www.w3.org/2000/01/rdf-schema#subClassOf' => 'http://class/A',
            ],
            [
                '@id' => 'http://class/D',
                'http://www.w3.org/2000/01/rdf-schema#subClassOf' => 'http://class/D',
            ],
        ]);

        $sut = $this->getSubjectUnderTest($graph);

        $result = $sut->buildNested('rdfs:subClassOf');

        // check
        self::assertEquals(
            [
                'http://class/A' => [
                    'http://class/B' => [
                        'http://class/C' => [],
                    ],
                    'http://class/D' => [],
                ],
            ],
            $result
        );
    }

    public function testBuildNested4(): void
    {
        $graph = new RDFGraph(new NamespaceHelper());

        /*
         * hierarchy looks like:
         *
         *      A                       <--- root
         *       `--- A
         */
        $graph->initialize([
            [
                '@id' => 'http://class/A',
                'http://www.w3.org/2000/01/rdf-schema#subClassOf' => 'http://class/A',
            ],
        ]);

        $sut = $this->getSubjectUnderTest($graph);

        $result = $sut->buildNested('rdfs:subClassOf');

        // check
        self::assertEquals(
            [],
            $result
        );
    }
}
