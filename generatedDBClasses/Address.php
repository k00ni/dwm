<?php

namespace DWM\DBClass;

/**
 * Auto generated. Changes will be overriden.
 *
 * @dwmClassId https://github.com/k00ni/dwm#Address
 * @dwmNodeshapeId https://github.com/k00ni/dwm#AddressShape
 */
class Address
{
    /**
     * @dwmTypeId http://www.w3.org/2001/XMLSchema#string
     * @dwmType string
     * @dwmMaxLength 255
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