<?php

declare(strict_types=1);

/**
 * Loads dwm.json file and returns array representation.
 *
 * @return array<string,string>
 *
 * @throws Exception if given dwm config file path does not exist
 */
function loadDwmJsonAndGetArrayRepresentation(string $dwmConfigFilePath): array
{
    if (file_exists($dwmConfigFilePath)) {
        $content = file_get_contents($dwmConfigFilePath);

        if (true === is_string($content)) {
            $result = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($result)) {
                return $result;
            }
            throw new Exception('Invalid content from dwm config file loaded.');
        }
        throw new Exception('Read of dwm config file failed.');
    }
    throw new Exception('Dwm config file path does not exist: '.$dwmConfigFilePath);
}
