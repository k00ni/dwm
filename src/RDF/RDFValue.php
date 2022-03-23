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
     * @param array<string,string> $data
     */
    public function __construct(array $data)
    {
        $namespaceHelper = new NamespaceHelper();

        // @id is either a blank node ID or URI
        if (isset($data['@id'])) {
            $this->id = $namespaceHelper->expandId($data['@id']);
        } elseif (isset($data['@value'])) {
            $this->value = $data['@value'];
        } else {
            throw new Exception('Either @value or @id must be set.');
        }

        if (isset($data['@type'])) {
            $this->type = $namespaceHelper->expandId($data['@type']);
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
