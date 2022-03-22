<?php

namespace DWM\RDF;

use Exception;

class RDFEntry
{
    private string $uri;

    /**
     * @var array<string>
     */
    private array $types = [];

    private array $propertyValues = [];

    /**
     * @param array<mixed> $jsonLDAsArray
     */
    public function __construct(array $jsonLDAsArray)
    {
        // @id
        $this->uri = $jsonLDAsArray['@id'];
        unset($jsonLDAsArray['@id']);

        // @type
        if (isset($jsonLDAsArray['@type'])) {
            $this->types = $jsonLDAsArray['@type'];
            unset($jsonLDAsArray['@type']);
        }

        /*
         * properties
         *
         * "http://www.w3.org/ns/shacl#minCount": [                         <=== $key
         *      {                                                           <=== $value
         *          "@value": "1",
         *          "@type": "http://www.w3.org/2001/XMLSchema#integer"
         *      }
         *  ],
         */
        foreach ($jsonLDAsArray as $propertyUri => $values) {
            if (!isset($this->propertyValues[$propertyUri])) {
                $this->propertyValues[$propertyUri] = [];
            }
            foreach ($values as $value) {
                /** @var array<string,string> */
                $value = $value;

                $this->propertyValues[$propertyUri][] = new RDFValue($value);
            }
        }
    }

    public function getTypes(): array
    {
        return $this->types;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * @return array<string>
     */
    public function getPropertUris(): array
    {

    }

    /**
     * @return array<string>
     */
    public function getPropertyValue(string $propertyUri): RDFValue
    {
        if (in_array($propertyUri, $this->getPropertUris())) {

        }

        throw new Exception('Property URI not found: '.$propertyUri);
    }
}
