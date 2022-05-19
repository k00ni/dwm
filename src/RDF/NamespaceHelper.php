<?php

declare(strict_types=1);

namespace DWM\RDF;

use Exception;

final class NamespaceHelper
{
    /**
     * @var array<string,string>
     */
    private array $namespaces = [
        'dc' => 'http://purl.org/dc/elements/1.1/',
        'dct' => 'http://purl.org/dc/terms/',
        'dwm' => 'https://github.com/k00ni/dwm#',
        'owl' => 'http://www.w3.org/2002/07/owl#',
        'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
        'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
        'schema' => 'https://schema.org/',
        'sh' => 'http://www.w3.org/ns/shacl#',
        'skos' => 'http://www.w3.org/2004/02/skos/core#',
        'xsd' => 'http://www.w3.org/2001/XMLSchema#',
    ];

    public function addNamespace(string $prefix, string $uri): void
    {
        if (!isset($this->namespaces[$prefix])) {
            $this->namespaces[$prefix] = $uri;
        } else {
            throw new Exception('Namespace is already registered.');
        }
    }

    public function hasNamespace(string $prefix): bool
    {
        return isset($this->namespaces[$prefix]);
    }

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
