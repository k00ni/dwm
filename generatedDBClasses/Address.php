<?php

namespace DWM\DBClass;

/**
 * Auto generated. Changes will be overriden.
 *
 * @dwm-class-id https://github.com/k00ni/dwm#Address
 * @dwm-nodeshape-id https://github.com/k00ni/dwm#AddressShape
 */
class Address
{
    /**
     * @dwm-type-id http://www.w3.org/2001/XMLSchema#string
     * @dwm-type string
     */
    private string $streetAddress;

    public function getStreetAddress(): string
    {
        return $this->streetAddress;
    }

    public function setStreetAddress(string $value): void
    {
        $this->streetAddress = $value;
    }

}