<?php

/**
 * @todo create separate classes folder: src/DWM
 */

function jsonLdNodeHasPredicate(stdClass $node, string $predicate): bool
{
    /** @var array<mixed> */
    $propertyValuePairs = get_object_vars($node);
    return true === isset($propertyValuePairs[$predicate]);
}

/**
 * @return array<string>
 */
function jsonLdGetReferencedUrisOfProperty(stdClass $node, string $predicate): array
{
    /** @var array<mixed> */
    $propertyValuePairs = get_object_vars($node);

    if (true === isset($propertyValuePairs[$predicate])) {
        /** @var array<\stdClass> */
        $entries = $propertyValuePairs[$predicate];

        /** @var array<string> */
        $result = array_map(function($entry) {
            /** @var array<mixed> */
            $propertyValuePairs = get_object_vars($entry);
            if (isset($propertyValuePairs['@id'])) {
                return $propertyValuePairs['@id'];
            } else {
                throw new Exception('No @id key found on: '.json_encode($propertyValuePairs));
            }
        }, $entries);

        return $result;
    } else {
        throw new Exception('$node does not have predicate: '. $predicate);
    }
}
