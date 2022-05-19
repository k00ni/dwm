<?php

declare(strict_types=1);

namespace DWM\RDF\PropertyHelper;

use DWM\RDF\RDFGraph;

class PropertyHelper
{
    private RDFGraph $graph;

    /**
     * @var array<string,string|null>
     */
    private array $knownResourceTitles = [];

    /**
     * @var array<string>
     */
    private array $rankedPropertyUriList;

    /**
     * @param array<string> $rankedPropertyUriList
     */
    public function __construct(array $rankedPropertyUriList)
    {
        $this->rankedPropertyUriList = $rankedPropertyUriList;
    }

    public function init(RDFGraph $graph): void
    {
        $this->graph = $graph;
    }

    public function getValue(string $subjectUri, ?string $language = null): ?string
    {
        if (isset($this->knownResourceTitles[$subjectUri.$language])) {
            return $this->knownResourceTitles[$subjectUri.$language];
        }

        $subGraph = $this->graph->getSubGraphWithEntriesWithIdOneOf([$subjectUri]);

        if (1 == count($subGraph->getEntries())) {
            $rdfEntry = $subGraph->getEntries()[0];

            // go through known ranked property list and check if it has a property
            foreach ($this->rankedPropertyUriList as $propertyUri) {
                if (false == $rdfEntry->hasProperty($propertyUri)) {
                    continue;
                }

                $values = $rdfEntry->getPropertyValues($propertyUri);

                foreach ($values as $rdfValue) {
                    if (
                        (null != $language && $rdfValue->getLanguage() == $language)
                        || null == $language
                    ) {
                        $this->knownResourceTitles[$subjectUri.$language] = $rdfValue->getIdOrValue();

                        return $rdfValue->getIdOrValue();
                    }
                }
            }
        }

        return null;
    }
}
