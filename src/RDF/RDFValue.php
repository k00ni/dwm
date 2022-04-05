<?php

declare(strict_types=1);

namespace DWM\RDF;

use Exception;

class RDFValue
{
    private ?string $id = null;
    private ?string $value = null;
    private ?string $type = null;

    /**
     * @param array<string,string|bool|int> $data
     */
    public function __construct(array $data, NamespaceHelper $namespaceHelper)
    {
        // @id is either a blank node ID or URI
        if (isset($data['@id'])) {
            $this->id = $namespaceHelper->expandId((string) $data['@id']);
        } elseif (isset($data['@value'])) {
            // boolean
            if (is_bool($data['@value'])) {
                $data['@value'] = true === $data['@value'] ? '1' : '0';
                if (!isset($data['@type'])) {
                    $data['@type'] = 'http:\/\/www.w3.org\/2001\/XMLSchema#boolean';
                }
            } elseif (is_int($data['@value'])) {
                // integer
                $data['@value'] = (string) $data['@value'];
                if (!isset($data['@type'])) {
                    $data['@type'] = 'http:\/\/www.w3.org\/2001\/XMLSchema#integer';
                }
            }

            $this->value = (string) $data['@value'];
        } else {
            throw new Exception('Either @value or @id must be set.');
        }

        if (isset($data['@type'])) {
            $this->type = $namespaceHelper->expandId((string) $data['@type']);
        }
    }

    public function __toString()
    {
        if (null != $this->id) {
            return '<'.$this->id.'>';
        } elseif (null != $this->value) {
            $str = '"'.$this->value.'"';
            if (null != $this->type) {
                $str .= '^^<'.$this->type.'>';
            }

            return $str;
        }

        return '';
    }

    public function getIdOrValue(): ?string
    {
        return $this->id ?? $this->value;
    }

    public function getType(): ?string
    {
        return $this->type;
    }
}
