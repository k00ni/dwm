<?php

namespace DWM\DBClass;

/**
 * Auto generated. Changes will be overriden.
 *
 * @dwm-class-id https://github.com/k00ni/dwm#Person
 * @dwm-nodeshape-id https://github.com/k00ni/dwm#PersonShape
 */
class Person
{
    /**
     * @dwm-type-id http://www.w3.org/2001/XMLSchema#string
     * @dwm-type string
     */
    private string $givenName;

    /**
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