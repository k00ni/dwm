<?php

declare(strict_types=1);

namespace DWM\RDF;

/**
 * Builds hierarchies in different forms, like nested, using a given property like rdfs:subClassOf.
 */
class PropertyBasedHierarchyBuilder
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
    public function buildNested(string $propertyUri): array
    {
        $index = [];
        $subGraph = $this->graph->getSubGraphWithEntriesWithProperty($propertyUri);

        /*
         * build an index which will look like:
         *
         * $index
         *
         *      [
         *          'A' => [
         *              'parent' => null,
         *              'children' => ['B', 'C'],
         *          ]
         *          ...
         *      ]
         */
        foreach ($subGraph->getEntries() as $rdfEntry) {
            foreach ($rdfEntry->getPropertyValues($propertyUri) as $rdfValue) {
                $parentUri = $rdfValue->getIdOrValue();
                $childUri = $rdfEntry->getId();

                // parent
                if (!isset($index[$parentUri])) {
                    $index[$parentUri] = ['parent' => null, 'children' => [$childUri]];
                } else {
                    $index[$parentUri]['children'][] = $childUri;
                }

                // child
                if (!isset($index[$childUri])) {
                    $index[$childUri] = ['parent' => $parentUri, 'children' => []];
                }

                // if a parent becomes a child
                if (null == $index[$childUri]['parent']) {
                    $index[$childUri]['parent'] = $parentUri;
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
        foreach ($index as $entryUri => $entryArray) {
            if (null == $entryArray['parent']) {
                $orderedList[$entryUri] = 0;
                $orderedListReversed[0][] = $entryUri;
            } else {
                // get position of parent
                if (isset($orderedList[$entryArray['parent']])) {
                    $orderedList[$entryUri] = $orderedList[$entryArray['parent']] + 1;

                    // remember reversed way
                    if (!isset($orderedListReversed[$orderedList[$entryUri]])) {
                        $orderedListReversed[$orderedList[$entryUri]] = [];
                    }
                    $orderedListReversed[$orderedList[$entryUri]][] = $entryUri;
                } else {
                    // collect all classes whose parent class is not yet in $orderedList
                    $uncoveredClasses[$entryUri] = $entryArray;
                }
            }
        }

        // handle uncovered classes
        foreach ($uncoveredClasses as $entryUri => $entryArray) {
            // get position of parent
            $orderedList[$entryUri] = $orderedList[$entryArray['parent']] + 1;

            // remember reversed way
            if (!isset($orderedListReversed[$orderedList[$entryUri]])) {
                $orderedListReversed[$orderedList[$entryUri]] = [];
            }
            $orderedListReversed[$orderedList[$entryUri]][] = $entryUri;
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
        foreach ($orderedListReversed[0] as $rootEntryUri) {
            $result[$rootEntryUri] = $this->setRelatedSubClasses(
                $rootEntryUri,
                $orderedListReversed,
                1, // next level
                $index
            );
        }

        return $result;
    }

    /**
     * @param array<int,array<string>>                                    $orderedListReversed
     * @param array<string, array<string, array<int,string>|string|null>> $index
     *
     * @return array<mixed>
     */
    private function setRelatedSubClasses(
        string $parentUri,
        array $orderedListReversed,
        int $level,
        array $index
    ): array {
        $resultPart = [];

        if (!isset($orderedListReversed[$level])) {
            return $resultPart;
        }

        foreach ($orderedListReversed[$level] as $entryUri) {
            // add class if their parent class matches
            if ($index[$entryUri]['parent'] == $parentUri) {
                $resultPart[$entryUri] = $this->setRelatedSubClasses(
                    $entryUri,
                    $orderedListReversed,
                    $level + 1,
                    $index
                );
            }
        }

        return $resultPart;
    }
}
