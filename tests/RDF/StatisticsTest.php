<?php

declare(strict_types=1);

namespace DWM\Tests\RDF;

use DWM\RDF\NamespaceHelper;
use DWM\RDF\RDFGraph;
use DWM\RDF\Statistics;
use DWM\Test\TestCase;

class StatisticsTest extends TestCase
{
    private function getSubjectUnderTest(): Statistics
    {
        return new Statistics();
    }

    private function getGraph(): RDFGraph
    {
        $graph = new RDFGraph(new NamespaceHelper());

        $graph->initialize([
            // classes
            [
                '@id' => 'http://class1',
                '@type' => 'http://www.w3.org/2002/07/owl#Class',
            ],
            [
                '@id' => 'http://class2',
                '@type' => 'http://www.w3.org/2002/07/owl#Class',
            ],
            [
                '@id' => 'http://class3',
                '@type' => 'http://www.w3.org/2002/07/owl#Class',
            ],
            [
                '@id' => 'http://class4',
                '@type' => 'http://www.w3.org/2000/01/rdf-schema#Class',
            ],
            // properties
            [
                '@id' => 'http://class1',
                '@type' => 'http://www.w3.org/2002/07/owl#DatatypeProperty',
            ],
            [
                '@id' => 'http://class2',
                '@type' => 'http://www.w3.org/2002/07/owl#ObjectProperty',
            ],
        ]);

        return $graph;
    }

    public function testUsage(): void
    {
        $sut = $this->getSubjectUnderTest();
        $sut->initAndCalculate($this->getGraph());

        self::assertEquals(
            [
                'number_of_classes' => 4,
                'number_of_object_properties' => 1,
                'number_of_data_type_properties' => 1,
            ],
            $sut->getStatistics()
        );
    }
}
