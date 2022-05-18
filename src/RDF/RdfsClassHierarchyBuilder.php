<?php

declare(strict_types=1);

namespace DWM\RDF;

/**
 * Builds class hierarchies in different forms, like nested, using rdfs:subClassOf relations.
 */
class RdfsClassHierarchyBuilder
{
    private RDFGraph $graph;

    public function __construct(RDFGraph $graph)
    {
        $this->graph = $graph;
    }

    /**
     * Builds a nested class hierarchy which looks like:
     *
     *      [
     *          'A' => [
     *              'B' => [],
     *              'C' => [
     *                  'D' => [],
     *              ],
     *          ]
     *      ]
     *
     * @return array<mixed>
     */
    public function buildNested(): array
    {
        $classIndex = [];
        $subGraph = $this->graph->getSubGraphWithEntriesWithProperty('rdfs:subClassOf');

        /*
         * build an index which will look like:
         *
         * $classIndex
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
                $parentClassUri = $rdfValue->getIdOrValue();
                $childClassUri = $rdfEntry->getId();

                // parent
                if (!isset($classIndex[$parentClassUri])) {
                    $classIndex[$parentClassUri] = ['parent_class' => null, 'sub_classes' => [$childClassUri]];
                } else {
                    $classIndex[$parentClassUri]['sub_classes'][] = $childClassUri;
                }

                // child
                if (!isset($classIndex[$childClassUri])) {
                    $classIndex[$childClassUri] = ['parent_class' => $parentClassUri, 'sub_classes' => []];
                }

                // if a parent becomes a child
                if (null == $classIndex[$childClassUri]['parent_class']) {
                    $classIndex[$childClassUri]['parent_class'] = $parentClassUri;
                }
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
        $orderedList = []; // TODO do we need this still?
        $orderedListReversed = [];
        $uncoveredClasses = [];

        // build root class level before going deeper
        foreach ($classIndex as $classUri => $classArray) {
            if (null == $classArray['parent_class']) {
                $orderedList[$classUri] = 0;
                $orderedListReversed[0][] = $classUri;
            } else {
                // get position of parent
                if (isset($orderedList[$classArray['parent_class']])) {
                    $orderedList[$classUri] = $orderedList[$classArray['parent_class']] + 1;

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
            $orderedList[$classUri] = $orderedList[$classArray['parent_class']] + 1;

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
                $classIndex
            );
        }

        return $result;
    }

    /**
     * @param array<int,array<string>>                                    $orderedListReversed
     * @param array<string, array<string, array<int,string>|string|null>> $classIndex
     *
     * @return array<mixed>
     */
    private function setRelatedSubClasses(
        string $parentClassUri,
        array $orderedListReversed,
        int $level,
        array $classIndex
    ): array {
        $resultPart = [];

        if (!isset($orderedListReversed[$level])) {
            return $resultPart;
        }

        foreach ($orderedListReversed[$level] as $classUri) {
            // add class if their parent class matches
            if ($classIndex[$classUri]['parent_class'] == $parentClassUri) {
                $resultPart[$classUri] = $this->setRelatedSubClasses(
                    $classUri,
                    $orderedListReversed,
                    $level + 1,
                    $classIndex
                );
            }
        }

        return $resultPart;
    }
}
