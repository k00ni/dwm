<?php

namespace DWM\RDF;

use Exception;

class RDFValue
{
    private string $value;
    private ?string $type;

    /**
     * @param array<string,string> $data
     */
    public function __construct(array $data)
    {
        $this->value = $data['@value'];

        if (isset($data['@type'])) {
            $this->type = $data['@type'];
        }
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getType(): ?string
    {
        return $this->type;
    }
}
