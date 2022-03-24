<?php

declare(strict_types=1);

namespace DWM\Tests\RDF;

use DWM\RDF\RDFGraph;
use DWM\Test\TestCase;
use Exception;

class RDFGraphTest extends TestCase
{
    /**
     * @var array<array<mixed>>
     */
    private array $rdfGraph1JsonArr;

    public function setUp(): void
    {
        parent::setUp();

        $file = $this->rootDir.'/test/data/rdfGraph1.jsonld';
        $fileContent = file_get_contents($file);

        if (is_string($fileContent)) {
            $rdfGraph1JsonArr = json_decode($fileContent, true);

            if (is_array($rdfGraph1JsonArr)) {
                $this->rdfGraph1JsonArr = $rdfGraph1JsonArr;
            } else {
                throw new Exception('Could not read rdfGraph1.jsonld.');
            }
        } else {
            throw new Exception('Read file content is not a string: '.$file);
        }
    }

    /**
     * @param array<array<mixed>> $arr
     */
    private function getSubjectUnderTest(array $arr): RDFGraph
    {
        return new RDFGraph($arr);
    }

    public function testGeneralUsage(): void
    {
        $sut = $this->getSubjectUnderTest($this->rdfGraph1JsonArr);

        // getNumberOfNodes
        self::assertCount(11, $sut);

        // get all classes
        $nodes = $sut->getSubGraphWithEntriesOfType('rdfs:Class');
        self::assertCount(2, $nodes);

        self::assertEquals('givenName', $sut->getPropertyNameForId('schema:givenName'));
    }

    public function testCaseLoadNodeShapeAndPropertyInfo(): void
    {
        $startGraph = $this->getSubjectUnderTest($this->rdfGraph1JsonArr);

        // 1. get all classes with a certain property value
        $subGraph = $startGraph->getSubGraphWithEntriesWithPropertyValue('dwm:isStoredInDatabase', 'true');
        self::assertCount(1, $subGraph);

        /** @var \DWM\RDF\RDFEntry */
        $class = $subGraph->getEntries()[0];

        // 2. get property information about
        $propInfo = $startGraph->getPropertyInfoForTargetClassByNodeShape($class->getId());
        self::assertEquals(
            [
                'givenName' => [
                    'propertyName' => 'givenName',
                    'datatype' => 'string',
                    'datatypeId' => 'http://www.w3.org/2001/XMLSchema#string',
                    'minCount' => '1',
                ],
            ],
            $propInfo
        );
    }
}
