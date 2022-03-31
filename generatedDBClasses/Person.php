<?php

namespace DWM\DBClass;

/**
 * Auto generated. Changes will be overriden.
 *
 * @dwmClassId https://github.com/k00ni/dwm#Person
 * @dwmNodeshapeId https://github.com/k00ni/dwm#PersonShape
 */
class Person
{
    /**
     * @dwmTypeId http://www.w3.org/2001/XMLSchema#string
     * @dwmType string
     * @dwmMaxLength 255
     */
    private string $givenName;

    /**
     * @dwmMinCount 1
     * @var array<Address>
     */
    private array $addressList = [];

    public function getGivenName(): string
    {
        return $this->givenName;
    }

    public function setGivenName(string $value): void
    {
        $this->givenName = $value;
    }

    public function addAddress(Address $entry): void
    {
        $this->addressList[] = $entry;
    }

    /**
     * @return array<Address>
     */
    public function getAddressList(): array
    {
        return $this->addressList;
    }
}