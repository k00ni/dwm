<?php

namespace DWM\RDF;

/**
 * Builds class hierarchies in different forms, like nested, using rdfs:subClassOf relations.
 */
class RdfsClassHierarchyBuilder
{
    private array $classIndex;

    private RDFGraph $graph;

    public function __construct(RDFGraph $graph)
    {
        $this->graph = $graph;
    }

    private function buildClassIndex(string $parentClassUri, string $childClassUri): void
    {
        // parent
        if (!isset($this->classIndex[$parentClassUri])) {
            $this->classIndex[$parentClassUri] = ['parent_class' => null, 'sub_classes' => [$childClassUri]];
        } else {
            $this->classIndex[$parentClassUri]['sub_classes'][] = $childClassUri;
        }

        // child
        if (!isset($this->classIndex[$childClassUri])) {
            $this->classIndex[$childClassUri] = ['parent_class' => $parentClassUri, 'sub_classes' => []];
        }

        // if a parent becomes a child
        if (null == $this->classIndex[$childClassUri]['parent_class']) {
            $this->classIndex[$childClassUri]['parent_class'] = $parentClassUri;
        }
    }

    public function buildNested(): array
    {
        $this->classIndex = [];
        $subGraph = $this->graph->getSubGraphWithEntriesWithProperty('rdfs:subClassOf');

        /*
         * build an index which will look like:
         *
         *      [
         *          'A' => [
         *              'parent_class' => null,
         *              'sub_classes' => ['B', 'C'],
         *          ]
         *          ...
         *      ]
         */
        foreach ($subGraph->getEntries() as $rdfEntry) {
            foreach ($rdfEntry->getPropertyValues('rdfs:subClassOf') as $rdfValue) {
                $this->buildClassIndex($rdfValue->getIdOrValue(), $rdfEntry->getId());
            }
        }

        /*
         * order index in a way that for each class related level in the tree is known:
         *
         * Ordering it this way prevents the need to reorganize the whole structure later on.
         *
         * $orderedList:
         *      [
         *          'A' => 0,
         *          'B' => 1,
         *          'C' => 2,
         *          ...
         *      ]
         *
         * $orderedListReversed:
         *      [
         *          0 => ['A'],
         *          1 => ['B', 'D'],
         *          2 => ['C', 'E'],
         *          ...
         *      ]
         */
        $orderedList = [];
        $orderedListReversed = [];
        $uncoveredClasses = [];

        // build root class level before going deeper
        foreach ($this->classIndex as $classUri => $classArray) {
            if (null == $classArray['parent_class']) {
                $orderedList[$classUri] = 0;
                $orderedListReversed[0][] = $classUri;
            } else {
                // get position of parent
                if (isset($orderedList[$classArray['parent_class']])) {
                    $orderedList[$classUri] = $orderedList[$classArray['parent_class']]+1;

                    // remember reversed way
                    if (!isset($orderedListReversed[$orderedList[$classUri]])) {
                        $orderedListReversed[$orderedList[$classUri]] = [];
                    }
                    $orderedListReversed[$orderedList[$classUri]][] = $classUri;
                } else {
                    // collect all classes whose parent class is not yet in $orderedList
                    $uncoveredClasses[$classUri] = $classArray;
                }
            }
        }

        // handle uncovered classes
        foreach ($uncoveredClasses as $classUri => $classArray) {
            // get position of parent
            $orderedList[$classUri] = $orderedList[$classArray['parent_class']]+1;

            // remember reversed way
            if (!isset($orderedListReversed[$orderedList[$classUri]])) {
                $orderedListReversed[$orderedList[$classUri]] = [];
            }
            $orderedListReversed[$orderedList[$classUri]][] = $classUri;
        }

        /*
         * build nested list by going through each level and loading related class URIs
         *
         * $orderedListReversed:
         *      [
         *          0 => ['A'],
         *          1 => ['B', 'D'],
         *          2 => ['C', 'E'],
         *          ...
         *      ]
         *
         * assumption: class parent is either null (= root class) or set in $result already!
         */
        $result = [];
        foreach ($orderedListReversed[0] as $classUri) {
            $result[$classUri] = $this->findAndUpdateParentClassEntry(
                $orderedListReversed,
                $index
            );
        }

        echo PHP_EOL;
        echo PHP_EOL;
        var_dump($result);

        return $result;
    }

    private function findAndUpdateParentClassEntry(
        array $orderedListReversed,
        int $index
    ): array {
        if (!isset($orderedListReversed[$index])) {
            return [];
        }

        $subResult = [];

        foreach ($orderedListReversed[$index] as $classUri) {
            // find
        }

        return $subResult;
    }
}
