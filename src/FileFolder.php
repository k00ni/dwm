<?php

declare(strict_types=1);

namespace DWM;

use Exception;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class FileFolder
{
    private string $rootPath;

    public function __construct()
    {
        $this->rootPath = __DIR__.'/..';
    }

    public function exists(string $filepath): bool
    {
        return file_exists($filepath);
    }

    public function getRootPath(): string
    {
        return $this->rootPath;
    }

    public function setRootPath(string $path): void
    {
        $this->rootPath = $path;
    }

    public function createFolder(string $folder_path, $permission = 0755): void
    {
        mkdir($folder_path, $permission);
    }

    public function getListOfFilesAndFolders(string $path): iterable
    {
        $list = array_diff(scandir($path), ['.', '..']);
        natcasesort($list);

        $result = [];

        foreach ($list as $entry) {
            $full_path = rtrim($path, '/').'/'.$entry;

            // get lower case file extension without .
            $file_ext = strtolower(substr($entry, strrpos($entry, '.') + 1));

            $result[] = [
                'name' => basename($entry),
                'type' => is_dir($full_path) ? 'folder' : 'file',
                'is_image' => !is_dir($full_path) && in_array($file_ext, ['png', 'jpg', 'jpeg', 'gif']),
            ];
        }

        return $result;
    }

    /**
     * @return string[]
     */
    public function getFilesInFolderRecursively(string $dirPath): iterable
    {
        if (!is_dir($dirPath)) {
            throw new Exception('Given path does not point to a directory.');
        }

        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dirPath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        $result = [];

        foreach ($entries as $path) {
            if (!$path->isDir() && !$path->isLink()) {
                $result[] = $path->getPathname();
            }
        }

        return $result;
    }

    public function removeFolderRecursively(string $dirPath): bool
    {
        if (!is_dir($dirPath)) {
            return false;
        }

        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dirPath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($entries as $path) {
            if ($path->isDir() && !$path->isLink()) {
                rmdir($path->getPathname());
            } else {
                unlink($path->getPathname());
            }
        }

        return rmdir($dirPath);
    }

    public function removeFile(string $filepath): bool
    {
        return unlink($filepath);
    }
}
