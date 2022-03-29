<?php

declare(strict_types=1);

namespace DWM\RDF;

use DWM\Exception\PropertyIdNotFoundException;
use Exception;

final class RDFEntry
{
    private string $id;

    private NamespaceHelper $namespaceHelper;

    /**
     * @var array<string>
     */
    private array $types = [];

    /**
     * @var array<string,array<RDFValue>>
     */
    private array $propertyValues = [];

    /**
     * @param array<mixed> $jsonLDAsArray
     */
    public function __construct(array $jsonLDAsArray)
    {
        $this->namespaceHelper = new NamespaceHelper();

        // @id
        /** @var string */
        $id = $jsonLDAsArray['@id'];
        $this->id = $id;
        unset($jsonLDAsArray['@id']);

        // @type
        if (isset($jsonLDAsArray['@type'])) {
            if (is_string($jsonLDAsArray['@type'])) {
                $jsonLDAsArray['@type'] = [$jsonLDAsArray['@type']];
            }

            /** @var array<string> */
            $types = $jsonLDAsArray['@type'];
            $this->types = $types;
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
        foreach ($jsonLDAsArray as $propertyId => $values) {
            if (!isset($this->propertyValues[$propertyId])) {
                $this->propertyValues[$propertyId] = [];
            }

            if (!is_array($values)) {
                $values = [$values];
            }

            /** @var array<mixed> */
            $values = $values;

            foreach ($values as $value) {
                /** @var array<string,string> */
                $value = $value;

                if (!is_array($value)) {
                    $value = ['@value' => $value];
                }

                $this->propertyValues[$propertyId][] = new RDFValue($value);
            }
        }
    }

    public function __toString()
    {
        $result = PHP_EOL;
        $result .= $this->getId();
        $result .= PHP_EOL;

        /** @var array<string> */
        $sub = [];

        foreach ($this->propertyValues as $propertyId => $values) {
            $str = '  ';
            $str .= $propertyId.' ';

            $valueList = [];
            foreach ($values as $value) {
                $valueList[] = (string) $value;
            }

            $str .= implode(', ', $valueList);
            $sub[] = $str;
        }

        $result .= implode(' ;'.PHP_EOL, $sub);
        $result .= ' .';

        return $result;
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return array<string>
     */
    public function getTypes(): array
    {
        return $this->types;
    }

    /**
     * @param array<string> $typeIds
     */
    public function hasTypeOneOf(array $typeIds): bool
    {
        foreach ($typeIds as $typeId) {
            $expandedTypeId = $this->namespaceHelper->expandId($typeId);
            if (in_array($expandedTypeId, $this->types, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string>
     */
    public function getPropertyIds(): array
    {
        return array_keys($this->propertyValues);
    }

    public function getPropertyValue(string $propertyId): RDFValue
    {
        $expandedPropertyId = $this->namespaceHelper->expandId($propertyId);
        $values = $this->getPropertyValues($expandedPropertyId);

        if (1 == count($values)) {
            return $values[0];
        } else {
            throw new Exception('More than one value found for property ID: '.$expandedPropertyId);
        }
    }

    /**
     * @return array<\DWM\RDF\RDFValue>
     */
    public function getPropertyValues(string $propertyId): array
    {
        $expandedPropertyId = $this->namespaceHelper->expandId($propertyId);
        if ($this->hasProperty($expandedPropertyId)) {
            return $this->propertyValues[$expandedPropertyId];
        }

        throw new PropertyIdNotFoundException('Property ID not found: '.$expandedPropertyId);
    }

    /**
     * @return array<int,string|null>
     */
    public function getRawPropertyValues(string $propertyId): array
    {
        $expandedPropertyId = $this->namespaceHelper->expandId($propertyId);
        if ($this->hasProperty($expandedPropertyId)) {
            $result = [];
            foreach ($this->propertyValues[$expandedPropertyId] as $value) {
                $result[] = $value->getIdOrValue();
            }

            return $result;
        }

        throw new PropertyIdNotFoundException('Property ID not found: '.$expandedPropertyId);
    }

    public function hasProperty(string $propertyId): bool
    {
        $expandedPropertyId = $this->namespaceHelper->expandId($propertyId);

        return in_array($expandedPropertyId, $this->getPropertyIds(), true);
    }

    public function hasPropertyValue(string $propertyId, string $value): bool
    {
        try {
            $values = $this->getPropertyValues($propertyId);

            if (0 < count($values)) {
                foreach ($values as $propValue) {
                    if ($propValue->getIdOrValue() == $value) {
                        return true;
                    }
                }
            }

            return false;
        } catch (PropertyIdNotFoundException $exceptionToBeIgnored) {
            return false;
        }
    }
}
