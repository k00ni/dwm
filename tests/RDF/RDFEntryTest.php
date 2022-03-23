<?php

declare(strict_types=1);

namespace DWM\Tests\RDF;

use DWM\RDF\RDFEntry;
use DWM\RDF\RDFValue;
use DWM\Test\TestCase;
use Exception;

class RDFEntryTest extends TestCase
{
    /**
     * @var array<mixed>
     */
    private array $rdfEntry1JsonArray;

    public function setUp(): void
    {
        parent::setUp();

        $file = $this->rootDir.'/test/data/rdfEntry1.jsonld';
        $fileContent = file_get_contents($file);

        if (is_string($fileContent)) {
            $rdfEntry1JsonArray = json_decode($fileContent, true);

            if (is_array($rdfEntry1JsonArray)) {
                $this->rdfEntry1JsonArray = $rdfEntry1JsonArray;
            } else {
                throw new Exception('Could not read rdfEntry1.jsonld.');
            }
        } else {
            throw new Exception('Read file content is not a string: '.$file);
        }
    }

    /**
     * @param array<mixed> $arr
     */
    private function getSubjectUnderTest(array $arr): RDFEntry
    {
        return new RDFEntry($arr);
    }

    public function testUsage(): void
    {
        $sut = $this->getSubjectUnderTest($this->rdfEntry1JsonArray);

        // getId
        self::assertEquals('_:b0', $sut->getId());

        // getTypes
        self::assertEquals(
            [
                'https://github.com/k00ni/dwm#Process',
            ],
            $sut->getTypes()
        );

        // getPropertyIds
        self::assertEquals(
            [
                'http://www.w3.org/ns/shacl#minCount',
                'http://www.w3.org/ns/shacl#path',
                'http://www.w3.org/ns/shacl#datatype',
            ],
            $sut->getPropertyIds()
        );

        /*
         * getPropertyValues
         */
        $values = $sut->getPropertyValues('http://www.w3.org/ns/shacl#path');
        self::assertCount(2, $values);
        // value1
        self::assertEquals('https://github.com/k00ni/dwm#classPath', $values[0]->getIdOrValue());
        // value2
        self::assertEquals('https://schema.org/givenName', $values[1]->getIdOrValue());

        /*
         * getPropertyValue
         */
        self::assertEquals(
            new RDFValue(['@value' => '1', '@type' => 'http://www.w3.org/2001/XMLSchema#integer']),
            $sut->getPropertyValue('http://www.w3.org/ns/shacl#minCount')
        );
    }

    public function testGetPropertyValueWithMultipleValues(): void
    {
        $msg = 'More than one value found for property ID: http://www.w3.org/ns/shacl#path';
        $this->expectExceptionMessage($msg);

        $sut = $this->getSubjectUnderTest($this->rdfEntry1JsonArray);
        $sut->getPropertyValue('http://www.w3.org/ns/shacl#path');
    }
}
