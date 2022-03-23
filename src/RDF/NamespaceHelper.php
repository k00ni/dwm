<?php

declare(strict_types=1);

namespace DWM\RDF;

final class NamespaceHelper
{
    /**
     * @var array<string,string>
     */
    private array $namespaces = [
        'dwm' => 'https://github.com/k00ni/dwm#',
        'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
        'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
        'schema' => 'https://schema.org/',
        'sh' => 'http://www.w3.org/ns/shacl#',
        'xsd' => 'http://www.w3.org/2001/XMLSchema#',
    ];

    public function expandId(string $id): string
    {
        $namespacePrefixes = array_keys($this->namespaces);

        // blank node
        if (str_contains($id, '_:')) {
            return $id;
        } else {
            foreach ($namespacePrefixes as $prefix) {
                if (str_contains($id, $prefix.':')) {
                    return str_replace($prefix.':', $this->namespaces[$prefix], $id);
                }
            }
        }

        return $id;
    }
}
