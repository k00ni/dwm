<?php

declare(strict_types=1);

/**
 * Loads dwm.json file and returns array representation.
 */
function loadDwmJsonAndGetArrayRepresentation(string $rootPath, string $dwmConfigFilename): array
{
    if (file_exists($rootPath)) {
        if (file_exists($rootPath.'/'.$dwmConfigFilename)) {
            $content = file_get_contents($rootPath.'/'.$dwmConfigFilename);

            return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } else {
            throw new Exception('File '.$dwmConfigFilename.' not found in directory: '.$rootPath);
        }
    } else {
        throw new Exception('Root path does not exist: '.$rootPath);
    }
}
