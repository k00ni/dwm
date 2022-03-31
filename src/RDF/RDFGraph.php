<?php

declare(strict_types=1);

namespace DWM\RDF;

use Countable;
use DWM\Exception\EntryNotFoundException;
use Exception;

class RDFGraph implements Countable
{
    private NamespaceHelper $namespaceHelper;

    /**
     * @var array<RDFEntry>
     */
    private array $rdfEntries;

    /**
     * @param array<array<mixed>> $jsonArray
     */
    public function __construct(array $jsonArray = [])
    {
        $this->namespaceHelper = new NamespaceHelper();

        $this->rdfEntries = array_map(function ($rdfJsonArrayEntry) {
            return new RDFEntry($rdfJsonArrayEntry);
        }, $jsonArray);
    }

    public function __toString()
    {
        $result = '';

        foreach ($this->rdfEntries as $rdfEntry) {
            $result .= (string) $rdfEntry;
        }

        return $result;
    }

    public function addRDFEntry(RDFEntry $rdfEntry): void
    {
        $this->rdfEntries[] = $rdfEntry;
    }

    /**
     * @see Countable
     */
    public function count(): int
    {
        return count($this->rdfEntries);
    }

    public function getEntry(string $id): RDFEntry
    {
        foreach ($this->rdfEntries as $rdfEntry) {
            if ($rdfEntry->getId() == $id) {
                return $rdfEntry;
            }
        }

        throw new EntryNotFoundException('Entry was not found: '.$id);
    }

    /**
     * @return array<RDFEntry>
     */
    public function getEntries(): array
    {
        return $this->rdfEntries;
    }

    /**
     * @return array<mixed>|null
     */
    public function getPropertyInfoForTargetClassByNodeShape(string $targetClassId): ?array
    {
        $subGraph = $this->getSubGraphWithEntriesWithPropertyValue('sh:targetClass', $targetClassId);

        if (1 == $subGraph->count()) {
            $nodeShape = $subGraph->getEntries()[0];
            // contains references to other nodes (each has property infos)
            $propValues = $nodeShape->getPropertyValues('sh:property');

            /**
             * IDs of nodes which hold property information
             *
             * @var array<string>
             */
            $relatedNodeIds = [];
            foreach ($propValues as $value) {
                if (isEmpty($value->getIdOrValue())) {
                    // ignore
                } else {
                    $relatedNodeIds[] = (string) $value->getIdOrValue();
                }
            }

            // get sub graph which only contains related node IDs
            $subGraph = $this->getSubGraphWithEntriesWithIdOneOf($relatedNodeIds);

            /*
             * build property info
             */
            /** @var array<string,array<string,string>> */
            $propertyInfo = [];
            foreach ($subGraph->getEntries() as $rdfEntry) {
                $newEntry = [];
                // property name
                $path = $rdfEntry->getPropertyValue('sh:path')->getIdOrValue();
                if (isEmpty($path)) {
                    throw new Exception('Value of sh:path is empty or null');
                } else {
                    $newEntry['propertyName'] = $this->getPropertyNameForId((string) $path);

                    foreach ([
                        'dwm:defaultValue' => 'defaultValue',
                        'sh:minCount' => 'minCount',
                        'sh:maxCount' => 'maxCount',
                        'dwm:dbIsPrimaryKey' => 'isPrimaryKey',
                        'dwm:dbIsAutoIncrement' => 'isAutoIncrement',
                        'dwm:listWithEntriesOfType' => 'listWithEntriesOfType',
                        // constraint
                        'dwm:constraintName' => 'constraintName',
                        'dwm:constraintColumnName' => 'constraintColumnName',
                        'dwm:constraintReferencedTable' => 'constraintReferencedTable',
                        'dwm:constraintReferencedTableColumnName' => 'constraintReferencedTableColumnName',
                        'dwm:constraintUpdateRule' => 'constraintUpdateRule',
                        'dwm:constraintDeleteRule' => 'constraintDeleteRule',
                    ] as $propertyId => $key) {
                        if ($rdfEntry->hasProperty($propertyId)) {
                            $newEntry[$key] = $rdfEntry->getPropertyValue($propertyId)->getIdOrValue();
                        }
                    }

                    // refine datatype, if set
                    if ($rdfEntry->hasProperty('sh:datatype')) {
                        // example: xsd:string
                        /** @var string */
                        $datatypeId = $rdfEntry->getPropertyValue('sh:datatype')->getIdOrValue();
                        $newEntry['datatypeId'] = $datatypeId;

                        // example: string
                        $pos = strpos($newEntry['datatypeId'], '#');
                        $pos = false === $pos ? 0 : $pos + 1;
                        $newEntry['datatype'] = substr($newEntry['datatypeId'], $pos);
                    }

                    // maxlength (e.g. used for INT(11) in MySQL)
                    if ($rdfEntry->hasProperty('sh:maxLength')) {
                        /** @var string */
                        $maxLength = $rdfEntry->getPropertyValue('sh:maxLength')->getIdOrValue();
                        $newEntry['maxLength'] = $maxLength;
                    }

                    // precision and scale
                    if ($rdfEntry->hasProperty('dwm:precision') && $rdfEntry->hasProperty('dwm:scale')) {
                        /** @var string */
                        $precision = $rdfEntry->getPropertyValue('dwm:precision')->getIdOrValue();
                        $newEntry['precision'] = $precision;
                        /** @var string */
                        $scale = $rdfEntry->getPropertyValue('dwm:scale')->getIdOrValue();
                        $newEntry['scale'] = $scale;
                    }

                    // mysqlColumnDataType
                    if ($rdfEntry->hasProperty('dwm:mysqlColumnDataType')) {
                        /** @var string */
                        $mysqlColumnDataType = $rdfEntry->getPropertyValue('dwm:mysqlColumnDataType')->getIdOrValue();
                        $newEntry['mysqlColumnDataType'] = $mysqlColumnDataType;
                    }

                    $propertyInfo[$newEntry['propertyName']] = $newEntry;
                }
            }

            return $propertyInfo;
        } elseif (1 < $subGraph->count()) {
            throw new Exception('Multiple NodeShape instances are not supported yet.');
        }

        return null;
    }

    public function getPropertyNameForId(string $id): ?string
    {
        $subGraph = $this->getSubGraphWithEntriesWithIdOneOf([$id]);

        if (1 == $subGraph->count()) {
            $soleEntry = $subGraph->getEntries()[0];

            return $soleEntry->getPropertyValue('dwm:propertyName')->getIdOrValue();
        }

        return $id;
    }

    public function getSubGraphWithEntriesOfType(string $typeId): RDFGraph
    {
        $subGraph = new self();

        foreach ($this->rdfEntries as $rdfEntry) {
            if ($rdfEntry->hasTypeOneOf([$typeId])) {
                $subGraph->addRDFEntry($rdfEntry);
            }
        }

        return $subGraph;
    }

    /**
     * @param array<string> $ids
     */
    public function getSubGraphWithEntriesWithIdOneOf(array $ids): RDFGraph
    {
        $subGraph = new self();

        foreach ($ids as $id) {
            foreach ($this->rdfEntries as $rdfEntry) {
                $expandedId = $this->namespaceHelper->expandId($id);
                if ($rdfEntry->getId() == $expandedId) {
                    $subGraph->addRDFEntry($rdfEntry);
                }
            }
        }

        return $subGraph;
    }

    public function getSubGraphWithEntriesWithPropertyValue(string $propertyId, string $value): RDFGraph
    {
        $subGraph = new self();

        foreach ($this->rdfEntries as $rdfEntry) {
            if ($rdfEntry->hasPropertyValue($propertyId, $value)) {
                $subGraph->addRDFEntry($rdfEntry);
            }
        }

        return $subGraph;
    }
}
