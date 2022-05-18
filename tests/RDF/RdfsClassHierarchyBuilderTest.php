<?php

declare(strict_types=1);

namespace DWM\Tests\RDF;

use DWM\RDF\NamespaceHelper;
use DWM\RDF\RDFEntry;
use DWM\RDF\RDFGraph;
use DWM\RDF\RdfsClassHierarchyBuilder;
use DWM\RDF\RDFValue;
use DWM\Test\TestCase;
use Exception;

class RdfsClassHierarchyBuilderTest extends TestCase
{
    private function getSubjectUnderTest(RDFGraph $graph): RdfsClassHierarchyBuilder
    {
        return new RdfsClassHierarchyBuilder($graph);
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

        $sut->buildNested();
    }

    public function testBuildNested2(): void
    {
        $graph = new RDFGraph(new NamespaceHelper());

        /*
         * hierarchy looks like:
         *
         *      A                      <--- root
         *       `--- B
         *       |     `--- C
         *       |
         *       `--- D
         *             `--- E
         *      A2
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
                'http://www.w3.org/2000/01/rdf-schema#subClassOf' => 'http://class/A1',
            ],
            [
                '@id' => 'http://class/B',
                'http://www.w3.org/2000/01/rdf-schema#subClassOf' => 'http://class/A1',
            ],
        ]);

        $sut = $this->getSubjectUnderTest($graph);

        $sut->buildNested();
    }
}