<?php

declare(strict_types=1);

namespace DWM\Process;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use DWM\Attribute\ProcessStep;
use DWM\DWMConfig;
use DWM\SimpleStructure\Process;
use Exception;

class GenerateKnowledgeBasedOnDBTables extends Process
{
    private Connection $connection;
    private bool $createFiles;

    private string $currentPath;

    private string $database;

    private DWMConfig $dwmConfig;

    /**
     * @var array<mixed>
     */
    private array $knowledgeEntries;

    /**
     * @var array<mixed>
     */
    private array $tableInformation;

    public function __construct(DWMConfig $dwmConfig, bool $createFiles)
    {
        parent::__construct();

        $cwd = getcwd();
        if (is_string($cwd)) {
            $this->currentPath = $cwd;
        } else {
            throw new Exception('getcwd() return false!');
        }

        $this->addStep('loadDwmJson');

        $this->addStep('loadTableInformation');

        $this->addStep('createKnowledgeEntries');

        $this->addStep('createKnowledgeFiles');

        $this->createFiles = $createFiles;
        $this->dwmConfig = $dwmConfig;
    }

    #[ProcessStep()]
    protected function loadDwmJson(): void
    {
        $this->dwmConfig->load($this->currentPath);

        $accessData = $this->dwmConfig->getGenerateKnowledgeBasedOnDatabaseTablesAccessData();

        $this->connection = DriverManager::getConnection($accessData);

        $this->database = $accessData['dbname'];
    }

    #[ProcessStep()]
    protected function loadTableInformation(): void
    {
        // get all tables of the database
        /** @var array<string> */
        $tables = $this->connection->fetchFirstColumn('SHOW TABLES;');

        // get information for each table
        $this->tableInformation = array_map(function ($tableName) {
            $newEntry = [];

            $newEntry['tableName'] = $tableName;

            // table itself
            $newEntry['describeResult'] = $this->connection->fetchAllAssociative('DESCRIBE `'.$tableName.'`');

            /*
             * foreign key information
             *
             * get information about other tables which reference this table
             */
            $sql = 'SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                      FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                     WHERE REFERENCED_TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME = ?;';
            $newEntry['foreignKeyInformation'] = $this->connection->fetchAllAssociative($sql, [$this->database, $tableName]);

            return $newEntry;
        }, $tables);
    }

    private function snakeStyleToCamelStyle(string $str): string
    {
        return lcfirst(str_replace('_', '', ucwords($str, '_')));
    }

    /**
     * @param array<string,string|null> $propertyArr
     *
     * @return array<string,string|bool|int>
     */
    private function getPropertyInformation(array $propertyArr): array
    {
        $newProperty = [];

        $newProperty['propertyName'] = $this->snakeStyleToCamelStyle((string) $propertyArr['Field']);

        // if NULL is not allowed => minCount == 1
        if ('NO' == $propertyArr['Null']) {
            $newProperty['minCount'] = 1;
        }

        // handle: varchar(255)
        if (str_contains((string) $propertyArr['Type'], 'varchar(')) {
            // name
            $newProperty['typeId'] = 'xsd:string';

            // max length
            $newProperty['maxLength'] = str_replace('varchar(', '', (string) $propertyArr['Type']);
            $newProperty['maxLength'] = str_replace(')', '', $newProperty['maxLength']);
        } elseif (str_contains((string) $propertyArr['Type'], 'int(')) {
            // name
            $newProperty['typeId'] = 'xsd:integer';

            // max length
            $newProperty['maxLength'] = str_replace('int(', '', (string) $propertyArr['Type']);
            $newProperty['maxLength'] = str_replace(')', '', $newProperty['maxLength']);
        } elseif (str_contains((string) $propertyArr['Type'], 'decimal(')) {
            // name
            $newProperty['typeId'] = 'xsd:double';

            preg_match('/\(([0-9]+),([0-9]+)\)/smi', (string) $propertyArr['Type'], $match);
            $newProperty['precision'] = $match[1];
            $newProperty['scale'] = $match[2];
        } elseif ('date' == $propertyArr['Type']) {
            // name
            $newProperty['typeId'] = 'xsd:date';
        } elseif ('datetime' == $propertyArr['Type']) {
            // name
            $newProperty['typeId'] = 'xsd:dateTime';
        } elseif ('double' == $propertyArr['Type']) {
            // name
            $newProperty['typeId'] = 'xsd:double';
        } elseif ('longtext' == $propertyArr['Type']) {
            // name
            $newProperty['typeId'] = 'xsd:string';
        } elseif ('text' == $propertyArr['Type']) {
            // name
            $newProperty['typeId'] = 'xsd:string';
        } else {
            throw new Exception('Unknown MySQL field type given: '.$propertyArr['Type']);
        }

        /*
         * default
         */
        if (null !== $propertyArr['Default']) {
            $newProperty['defaultValue'] = $propertyArr['Default'];
        }

        /*
         * key infos
         */
        if ('PRI' == $propertyArr['Key']) {
            $newProperty['dbIsPrimaryKey'] = true;
        }
        if ('auto_increment' == $propertyArr['Extra']) {
            $newProperty['dbIsAutoIncrement'] = true;
        }

        return $newProperty;
    }

    #[ProcessStep()]
    protected function createKnowledgeEntries(): void
    {
        $this->knowledgeEntries = array_map(function ($tableInfo) {
            /** @var array<mixed> */
            $tableInfo = $tableInfo;

            $newEntry = [];

            /** @var string */
            $tableName = $tableInfo['tableName'];
            $name = ucfirst($this->snakeStyleToCamelStyle($tableName));

            // file name
            $newEntry['fileName'] = $name.'.jsonld';

            // class name
            $newEntry['className'] = $name;

            // properties
            $newEntry['properties'] = [];
            /** @var array<mixed> */
            $describeResult = $tableInfo['describeResult'];
            foreach ($describeResult as $propertyArr) {
                /** @var array<string,string> */
                $propertyArr = $propertyArr;
                $newEntry['properties'][] = $this->getPropertyInformation($propertyArr);
            }

            // relations (1-1, 1-n, n-m relations)
            $newEntry['relations'] = [];
            /** @var array<int,array<string,string>> */
            $foreignKeyInfoList = $tableInfo['foreignKeyInformation'];
            foreach ($foreignKeyInfoList as $foreignKeyInfo) {
                $newEntry['relations'][] = [
                    'relatedTable' => $foreignKeyInfo['TABLE_NAME'],
                    'fieldNameInRelatedTable' => $foreignKeyInfo['COLUMN_NAME'],
                    'fieldNameInThisTable' => $foreignKeyInfo['REFERENCED_COLUMN_NAME'],
                ];
            }

            return $newEntry;
        }, $this->tableInformation);
    }

    #[ProcessStep()]
    protected function createKnowledgeFiles(): void
    {
        $prefix = $this->dwmConfig->getDefaultNamespacePrefixForKnowledgeBasedOnDatabaseTables();
        $prefixUri = $this->dwmConfig->getDefaultNamespaceUriForKnowledgeBasedOnDatabaseTables();

        $data = [];
        array_map(function ($knowledgeEntry) use (&$data, $prefix, $prefixUri) {
            /** @var array<mixed> */
            $knowledgeEntry = $knowledgeEntry;

            // context
            $data['@context'] = [
                $prefix => $prefixUri,
                'dwm' => 'https://github.com/k00ni/dwm#',
                'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
                'schema' => 'https://schema.org/',
                'sh' => 'http://www.w3.org/ns/shacl#',
                'xsd' => 'http://www.w3.org/2001/XMLSchema#',
            ];

            // graph
            $data['@graph'] = [];

            // > class
            $data['@graph'][] = [
                '@id' => $prefix.':'.$knowledgeEntry['className'],
                '@type' => 'rdfs:Class',
                'rdfs:label' => $knowledgeEntry['className'],
                'dwm:className' => $knowledgeEntry['className'],
                'dwm:isStoredInDatabase' => true,
            ];

            // > NodeShape
            $shape = [
                '@id' => $prefix.':'.$knowledgeEntry['className'].'Shape',
                '@type' => 'sh:NodeShape',
                'sh:targetClass' => [
                    '@id' => $prefix.':'.$knowledgeEntry['className'],
                ],
                'sh:property' => [],
            ];

            /** @var array<array<string,string>> */
            $properties = $knowledgeEntry['properties'];
            /** @var array<array<string,string>> */
            $propertyIdToName = [];
            // add property information to shape
            foreach ($properties as $property) {
                /** @var array<string,string> */
                $property = $property;
                $newEntry = [];

                // sh:path
                $propertyId = $prefix.':'.$property['propertyName'];
                $newEntry['sh:path'] = ['@id' => $propertyId];
                $propertyIdToName[$propertyId] = $property['propertyName'];

                // sh:datatype
                $newEntry['sh:datatype'] = ['@id' => $property['typeId']];

                // sh:minCount
                if (isset($property['minCount'])) {
                    $newEntry['sh:minCount'] = $property['minCount'];
                }

                foreach (['dbIsPrimaryKey', 'dbIsAutoIncrement', 'defaultValue'] as $key) {
                    if (isset($property[$key])) {
                        $newEntry['dwm:'.$key] = $property[$key];
                    }
                }

                $shape['sh:property'][] = $newEntry;
            }

            /*
             * add relational information
             */
            /** @var array<mixed> */
            $relations = $knowledgeEntry['relations'];
            foreach ($relations as $relation) {
                /** @var array<string,string> */
                $relation = $relation;
                /** @var string */
                $relatedTable = $relation['relatedTable'];
                /** @var string */
                $fieldNameInRelatedTable = $relation['fieldNameInRelatedTable'];
                /** @var string */
                $fieldNameInThisTable = $relation['fieldNameInThisTable'];

                $newEntry = [];

                $propertyName = $this->snakeStyleToCamelStyle($relatedTable).'List';
                $propertyId = $prefix.':'.$propertyName;

                $newEntry['sh:path'] = ['@id' => $propertyId];
                $propertyIdToName[$propertyId] = $propertyName;

                $newEntry['dwm:fieldNameInRelatedTable'] = $fieldNameInRelatedTable;
                $newEntry['dwm:fieldNameInThisTable'] = $fieldNameInThisTable;
                $newEntry['dwm:datatype'] = ucfirst($this->snakeStyleToCamelStyle($relatedTable)).'[]';

                $shape['sh:property'][] = $newEntry;
            }

            $data['@graph'][] = $shape;

            /*
             * property names
             */
            foreach ($propertyIdToName as $propertyId => $propertyName) {
                $data['@graph'][] = [
                    '@id' => $propertyId,
                    'dwm:propertyName' => $propertyName,
                ];
            }

            /*
             * pretty print and put into a file
             */
            if ($this->createFiles) {
                $data = json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_IGNORE);
                if (false == $data) {
                    throw new Exception('Could not JSON encode $data.');
                } else {
                    $file = $this->dwmConfig->getFolderPathForKnowledgeBasedOnDatabaseTables().'/';

                    /** @var string */
                    $filename = $knowledgeEntry['fileName'];
                    $file .= $filename;
                    file_put_contents($file, $data);
                }
            }
        }, $this->knowledgeEntries);

        $this->result = $data;
    }
}
