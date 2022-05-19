<?php

declare(strict_types=1);

namespace DWM\RDF;

/**
 * Provides statistical information about a given graph.
 */
class Statistics
{
    private RDFGraph $graph;

    /**
     * @var array<string,int>
     */
    private array $statistics = [];

    public function initAndCalculate(RDFGraph $graph): void
    {
        $this->graph = $graph;

        $this->calculate();
    }

    private function calculate(): void
    {
        $this->statistics['number_of_classes'] = $this->calculateNumberOfClasses();

        $this->statistics['number_of_object_properties'] = $this->calculateNumberOfObjectProperties();
        $this->statistics['number_of_data_type_properties'] = $this->calculateNumberOfDataTypeProperties();
    }

    private function calculateNumberOfClasses(): int
    {
        $subGraph1 = $this->graph->getSubGraphWithEntriesOfType('owl:Class');
        $subGraph2 = $this->graph->getSubGraphWithEntriesOfType('rdfs:Class');

        return $subGraph1->count() + $subGraph2->count();
    }

    private function calculateNumberOfDataTypeProperties(): int
    {
        return $this->graph->getSubGraphWithEntriesOfType('owl:DatatypeProperty')->count();
    }

    private function calculateNumberOfObjectProperties(): int
    {
        return $this->graph->getSubGraphWithEntriesOfType('owl:ObjectProperty')->count();
    }

    /**
     * @return array<string,int>
     */
    public function getStatistics(): array
    {
        return $this->statistics;
    }
}
