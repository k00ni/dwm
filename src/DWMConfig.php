<?php

declare(strict_types=1);

namespace DWM;

use Exception;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * This class was created to avoid messy type-hint operations on array structures.
 * It helps to keep a clean code base and avoid PHPStan issues, especially in cases where the schema changes.
 */
class DWMConfig
{
    /**
     * @todo move to JSONLD
     */
    private string $dwmConfigFilename = 'dwm.json';

    private ?string $defaultNamespacePrefixForKnowledgeBasedOnDatabaseTables = null;
    private ?string $defaultNamespaceUriForKnowledgeBasedOnDatabaseTables = null;

    private ?string $fileWithDatabaseAccessData = null;

    /**
     * @var array<string,string>|null
     */
    private ?array $generateKnowledgeBasedOnDatabaseTablesAccessData = null;

    private ?string $folderPathForKnowledgeBasedOnDatabaseTables = null;

    private ?string $folderPathForSqlMigrationFiles = null;

    private ?string $generatedDBClassFilesPHPNamespace = null;
    private ?string $generatedDBClassFilesPath = null;

    /**
     * A list of all files which are in one of the related knowledge folders.
     *
     * @var array<string>
     */
    private array $knowledgeFilePaths = [];

    /**
     * @var array<string>
     */
    private array $knowledgeFolderPaths = [];

    private ?string $mergedKnowledgeNtFilePath = null;
    private ?string $mergedKnowledgeJsonLDFilePath = null;

    private ?string $pathToJenaShaclBinFile = null;

    private string $prefix = 'https://github.com/k00ni/dwm#';

    private ?string $resultFolderPath = null;

    public function getDefaultNamespacePrefixForKnowledgeBasedOnDatabaseTables(): ?string
    {
        return $this->defaultNamespacePrefixForKnowledgeBasedOnDatabaseTables;
    }

    public function getDefaultNamespaceUriForKnowledgeBasedOnDatabaseTables(): ?string
    {
        return $this->defaultNamespaceUriForKnowledgeBasedOnDatabaseTables;
    }

    public function getFileWithDatabaseAccessData(): ?string
    {
        return $this->fileWithDatabaseAccessData;
    }

    public function getFolderPathForKnowledgeBasedOnDatabaseTables(): ?string
    {
        return $this->folderPathForKnowledgeBasedOnDatabaseTables;
    }

    public function getFolderPathForSqlMigrationFiles(): ?string
    {
        return $this->folderPathForSqlMigrationFiles;
    }

    public function getGeneratedDBClassFilesPath(): ?string
    {
        return $this->generatedDBClassFilesPath;
    }

    public function getGeneratedDBClassFilesPHPNamespace(): ?string
    {
        return $this->generatedDBClassFilesPHPNamespace;
    }

    /**
     * @return array<string,string>|null
     */
    public function getGenerateKnowledgeBasedOnDatabaseTablesAccessData(): ?array
    {
        return $this->generateKnowledgeBasedOnDatabaseTablesAccessData;
    }

    /**
     * @return array<string>
     */
    public function getKnowledgeFilePaths(): array
    {
        return $this->knowledgeFilePaths;
    }

    /**
     * @return array<string>
     */
    public function getKnowledgeFolderPaths(): array
    {
        return $this->knowledgeFolderPaths;
    }

    /**
     * @return ?string
     */
    public function getMergedKnowledgeJsonLDFilePath(): ?string
    {
        return $this->mergedKnowledgeJsonLDFilePath;
    }

    /**
     * @return ?string
     */
    public function getMergedKnowledgeNtFilePath(): ?string
    {
        return $this->mergedKnowledgeNtFilePath;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function getResultFolderPath(): ?string
    {
        return $this->resultFolderPath;
    }

    public function getPathToJenaShaclBinFile(): ?string
    {
        return $this->pathToJenaShaclBinFile;
    }

    /**
     * Loads dwm.json file.
     *
     * @throws Exception if given dwm config file path does not exist
     */
    public function load(string $currentPath): void
    {
        $path = $currentPath.'/'.$this->dwmConfigFilename;

        if (file_exists($path)) {
            $content = file_get_contents($path);

            if (true === is_string($content)) {
                $result = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($result)) {
                    $this->includeArray($result, $currentPath);

                    return;
                }
                throw new Exception('Invalid content from dwm config file loaded.');
            }
            throw new Exception('Read of dwm config file failed.');
        }
        throw new Exception('Dwm config file path does not exist: '.$path);
    }

    private function collectKnowledgeFilePaths(): void
    {
        $files = [];
        array_map(function ($knowledgeFolderPath) use (&$files) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($knowledgeFolderPath, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $path) {
                /** @var \SplFileInfo */
                $path = $path;
                if ($path->isDir()) {
                } elseif ($path->isLink()) {
                } elseif (str_contains($path->getPathname(), '.jsonld')) {
                    $files[] = $path->getPathname();
                }
            }
        }, $this->knowledgeFolderPaths);

        $this->knowledgeFilePaths = $files;
    }

    /**
     * @param array<mixed> $data
     */
    private function includeArray(array $data, string $currentPath): void
    {
        /** @var array<mixed> */
        $location = $data['knowledgeLocation'];

        /*
         * knowledge folder paths
         */
        /** @var array<string> */
        $knowledgeFolderPaths = $location['folders'];
        $this->knowledgeFolderPaths = $knowledgeFolderPaths;

        // set current path as prefix for each knowledge folder path
        $this->knowledgeFolderPaths = array_map(function ($path) use ($currentPath) {
            return $currentPath.'/'.$path;
        }, $this->knowledgeFolderPaths);

        $this->collectKnowledgeFilePaths();

        /**
         * file paths to merged knowledge
         */
        /** @var array<mixed> */
        $mergedKnowledgeFile = $data['mergedKnowledgeFile'];
        /** @var string */
        $jsonLDPath = $mergedKnowledgeFile['jsonLDFilePath'];
        /** @var string */
        $ntPath = $mergedKnowledgeFile['ntFilePath'];

        $this->mergedKnowledgeJsonLDFilePath = $jsonLDPath;
        $this->mergedKnowledgeNtFilePath = $ntPath;

        /**
         * result folder path
         */
        /** @var array<mixed> */
        $resultFolder = $data['resultFolder'];
        /** @var string */
        $path = $resultFolder['path'];
        $this->resultFolderPath = $path;

        /*
         * result folder path
         */
        if (isset($data['jenaShaclBinFile'])) {
            /** @var array<mixed> */
            $jenaShaclBinFile = $data['jenaShaclBinFile'];
            /** @var string|null */
            $pathToJenaShaclBinFile = $jenaShaclBinFile['path'] ?? null;
            if (is_string($pathToJenaShaclBinFile) && file_exists($pathToJenaShaclBinFile)) {
                $this->pathToJenaShaclBinFile = $pathToJenaShaclBinFile;
            } else {
                throw new Exception('Jena SHACL bin file not found: '.$pathToJenaShaclBinFile);
            }
        }

        /*
         * generated DB classes
         */
        if (isset($data['generatedDBClassFiles'])) {
            /** @var array<string,string> */
            $generatedDBClassFiles = $data['generatedDBClassFiles'];

            // path
            /** @var string|null */
            $generatedDBClassFilesPath = $generatedDBClassFiles['path'] ?? null;
            if (is_string($generatedDBClassFilesPath) && file_exists($generatedDBClassFilesPath)) {
                $this->generatedDBClassFilesPath = $generatedDBClassFilesPath;
            } else {
                throw new Exception('Path to generated DB class files not found: '.$generatedDBClassFilesPath);
            }

            // PHP namespace
            /** @var string|null */
            $generatedDBClassFilesPHPNamespace = $generatedDBClassFiles['phpNamespace'] ?? null;
            $this->generatedDBClassFilesPHPNamespace = $generatedDBClassFilesPHPNamespace;
        }

        /*
         * generateKnowledgeBasedOnDatabaseTables
         */
        if (isset($data['generateKnowledgeBasedOnDatabaseTables'])) {
            /** @var array<string,string> */
            $genKnowledgeDatabaseTables = $data['generateKnowledgeBasedOnDatabaseTables'];

            // access data file
            /** @var string|null */
            $fileWithAccessData = $genKnowledgeDatabaseTables['fileWithAccessData'] ?? null;
            if (is_string($fileWithAccessData) && file_exists($fileWithAccessData)) {
                /** @var array<string,string> */
                $accessData = (array) require $fileWithAccessData;
                $accessData['driver'] = 'pdo_mysql';

                $this->generateKnowledgeBasedOnDatabaseTablesAccessData = $accessData;
            } else {
                throw new Exception('Path to access file not found: '.$fileWithAccessData);
            }

            // knowledge folder for new files
            /** @var string|null */
            $folderPathForKnowledgeBasedOnDatabaseTables = $genKnowledgeDatabaseTables['knowledgeFolder'] ?? null;
            if (is_string($folderPathForKnowledgeBasedOnDatabaseTables) && file_exists($folderPathForKnowledgeBasedOnDatabaseTables)) {
                $this->folderPathForKnowledgeBasedOnDatabaseTables = $folderPathForKnowledgeBasedOnDatabaseTables;
            } else {
                $msg = 'Path to knowledge folder for generate knowledge not found: '.$folderPathForKnowledgeBasedOnDatabaseTables;
                throw new Exception($msg);
            }

            // default namespace prefix + URI
            /** @var string|null */
            $defaultNamespacePrefixForKnowledgeBasedOnDatabaseTables = $genKnowledgeDatabaseTables['defaultNamespacePrefix'] ?? null;
            $this->defaultNamespacePrefixForKnowledgeBasedOnDatabaseTables = $defaultNamespacePrefixForKnowledgeBasedOnDatabaseTables;

            /** @var string|null */
            $defaultNamespaceUriForKnowledgeBasedOnDatabaseTables = $genKnowledgeDatabaseTables['defaultNamespaceUri'] ?? null;
            $this->defaultNamespaceUriForKnowledgeBasedOnDatabaseTables = $defaultNamespaceUriForKnowledgeBasedOnDatabaseTables;
        }

        /*
         * generateDBSchemaBasedOnKnowledge
         */
        if (isset($data['generateDBSchemaBasedOnKnowledge'])) {
            /** @var array<string,string> */
            $generateDBSchemaBasedOnKnowledge = $data['generateDBSchemaBasedOnKnowledge'];

            // folderPathForSqlMigrationFiles
            /** @var string|null */
            $folderPathForSqlMigrationFiles = $generateDBSchemaBasedOnKnowledge['folderPathForSqlMigrationFiles'] ?? null;
            if (is_string($folderPathForSqlMigrationFiles) && file_exists($folderPathForSqlMigrationFiles)) {
                $this->folderPathForSqlMigrationFiles = $folderPathForSqlMigrationFiles;
            } else {
                $msg = 'Path to folder for SQL migration files not found: '.$folderPathForSqlMigrationFiles;
                throw new Exception($msg);
            }
        }
    }
}
