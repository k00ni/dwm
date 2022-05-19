<?php

declare(strict_types=1);

namespace DWM\RDF\PropertyHelper;

/**
 * A property helper which is focusing on resource titles and names.
 */
class TitleHelper extends PropertyHelper
{
    public function __construct()
    {
        parent::__construct([
            // rdfs
            'http://www.w3.org/2000/01/rdf-schema#label',
            // dublin core
            'http://purl.org/dc/terms/title',
            'http://purl.org/dc/elements/1.1/title',
            // skos
            'http://www.w3.org/2004/02/skos/core#prefLabel',
            'http://www.w3.org/2004/02/skos/core#altLabel',
        ]);
    }
}
