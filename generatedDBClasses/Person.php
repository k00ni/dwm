<?php

namespace DWM\DBClass;

/**
 * Auto generated. Changes will be overriden.
 *
 * @dwm-class-id https://schema.org/Person
 * @dwm-nodeshape-id https://schema.org/PersonShape
 */
class Person
{
    /**
     * @dwm-type-id http://www.w3.org/2001/XMLSchema#string
     * @dwm-type string
     */
    private string $givenName;

    public function getGivenName(): string
    {
        return $this->givenName;
    }

    public function setGivenName(string $value): void
    {
        $this->givenName = $value;
    }

}