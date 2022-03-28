<?php

declare(strict_types=1);

use DWM\FileFolder;

$rootDir = __DIR__.'/..';

require $rootDir.'/vendor/autoload.php';

$fileFolder = new FileFolder();

/*
 * if cache/test folder doesn't exist, create it
 */
$cacheTestFolder = $rootDir.'/cache/knowledge';
if ($fileFolder->exists($cacheTestFolder)) {
    $fileFolder->removeFolderRecursively($cacheTestFolder);
}

foreach ([
    $rootDir.'/cache',
    $rootDir.'/cache/knowledge',
] as $folder) {
    if (!$fileFolder->exists($folder)) {
        $fileFolder->createFolder($folder, 0755);
    }
}
