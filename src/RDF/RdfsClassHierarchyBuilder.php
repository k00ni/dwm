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
         *          'D' => 1,
         *          'E' => 2,
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
         *
         * $result will be looking like this in the end:
         *
         *          l=0  l=1    l=2...
         *           |    |      |
         *           v    v      v
         *      [
         *          'A' => [
         *              'B' => ['C' => []],
         *              'D' => ['E' => []],
         *              ...
         *          ]
         *      ]
         */
        $result = [];
        foreach ($orderedListReversed[0] as $rootClassUri) {
            $result[$rootClassUri] = $this->setRelatedSubClasses(
                $rootClassUri,
                $orderedListReversed,
                1, // next level
            );
        }

        return $result;
    }

    private function setRelatedSubClasses(
        string $parentClassUri,
        array $orderedListReversed,
        int $level
    ): array {
        $resultPart = [];

        if (!isset($orderedListReversed[$level])) {
            return $resultPart;
        }

        foreach ($orderedListReversed[$level] as $classUri) {
            // add class if their parent class matches
            if ($this->classIndex[$classUri]['parent_class'] == $parentClassUri) {
                $resultPart[$classUri] = $this->setRelatedSubClasses(
                    $classUri,
                    $orderedListReversed,
                    $level+1
                );
            }
        }

        return $resultPart;
    }
}
