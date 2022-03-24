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

                    // datatype
                    foreach ([
                        'sh:datatype' => 'datatype',
                        'sh:minCount' => 'minCount',
                        'sh:maxCount' => 'maxCount',
                    ] as $propertyId => $key) {
                        if ($rdfEntry->hasProperty($propertyId)) {
                            $newEntry[$key] = $rdfEntry->getPropertyValue($propertyId)->getIdOrValue();
                        }
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