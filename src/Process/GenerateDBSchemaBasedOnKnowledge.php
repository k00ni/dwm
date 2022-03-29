<?php

declare(strict_types=1);

namespace DWM\Process;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use DWM\Attribute\ProcessStep;
use DWM\DWMConfig;
use DWM\RDF\RDFGraph;
use DWM\SimpleStructure\Process;
use Exception;

class GenerateDBSchemaBasedOnKnowledge extends Process
{
    private Connection $connection;

    private string $currentPath;

    private string $database;

    private DWMConfig $dwmConfig;

    private RDFGraph $graph;

    private array $expectedDatabaseDescription = [];

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

        $this->addStep('createGraphBasedOnMergedKnowledge');

        $this->addStep('generateExpectedDatabaseDescription');

        $this->addStep('generateCurrentDatabaseDescription');

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
    protected function createGraphBasedOnMergedKnowledge(): void
    {
        $mergedFilePath = $this->dwmConfig->getMergedKnowledgeJsonLDFilePath();

        if (true === is_string($mergedFilePath) && file_exists($mergedFilePath)) {
            $content = file_get_contents($mergedFilePath);
            if (is_string($content)) {
                $jsonArr = json_decode($content, true);
                if (is_array($jsonArr)) {
                    $this->graph = new RDFGraph($jsonArr);
                } else {
                    throw new Exception('Decoded JSON is not an array.');
                }
            } else {
                throw new Exception('Could not read content of '.$mergedFilePath);
            }
        } else {
            throw new Exception('Merged knowledge file doesn not exist: '.$mergedFilePath);
        }
    }

    #[ProcessStep()]
    protected function generateExpectedDatabaseDescription(): void
    {
        $subGraph = $this->graph->getSubGraphWithEntriesWithPropertyValue('dwm:isStoredInDatabase', 'true');

        $this->classConfig = array_map(function ($rdfEntry) {
            /** @var array<string,string|array<mixed>> */
            $newEntry = [];

            /** @var \DWM\RDF\RDFEntry */
            $rdfEntry = $rdfEntry;

            $newEntry = ['columns' => []];

            // table name
            $newEntry['tableName'] = $rdfEntry->getPropertyValue('dwm:className')->getIdOrValue();

            // get related NodeShape
            $propertyInfo = $this->graph->getPropertyInfoForTargetClassByNodeShape($rdfEntry->getId());
            foreach ($propertyInfo as $property) {
                /** @var array<string,string> */
                $column = [];
                $column['name'] = $property['propertyName'];

                // primary key
                if (isset($property['isPrimaryKey'])) {
                    $column['isPrimaryKey'] = 'true' == $property['isPrimaryKey'];
                    // auto increment
                    if (isset($property['isAutoIncrement'])) {
                        $column['isAutoIncrement'] = 'true' == $property['isAutoIncrement'];
                    }
                }

                // type
                if ('integer' == $property['datatype']) {
                    $column['type'] = 'INT';
                } elseif ('string' == $property['datatype']) {
                    $column['type'] = 'VARCHAR(255)';
                } elseif ('double' == $property['datatype']) {
                    $column['type'] = 'DOUBLE';
                } elseif ('float' == $property['datatype']) {
                    $column['type'] = 'FLOAT';
                } else {
                    throw new Exception('Unknown datatype given: '.$property['datatype']);
                }

                // IS NULL
                if (isset($property['minCount']) && 0 < (int) $property['minCount']) {
                    $column['canBeNull'] = false;
                } else {
                    $column['canBeNull'] = true;
                }

                // default value
                if (isset($property['defaultValue'])) {
                    $column['defaultValue'] = $property['defaultValue'];
                }

                // constraint
                if (isset($property['constraintName'])) {
                    $column['constraint'] = [
                        'name' => $property['constraintName'],
                        'columnName' => $property['constraintColumnName'],
                        'referencedTable' => $property['constraintReferencedTable'],
                        'referencedTableColumnName' => $property['constraintReferencedTableColumnName'],
                        'updateRule' => $property['constraintUpdateRule'],
                        'deleteRule' => $property['constraintDeleteRule'],
                    ];
                }

                $newEntry['columns'][] = $column;
            }

            $this->expectedDatabaseDescription[] = $newEntry;

            return $newEntry;
        }, $subGraph->getEntries());

        $this->result = [];
        $this->result['expectedDatabaseDescription'] = $this->expectedDatabaseDescription;
    }

    #[ProcessStep()]
    protected function generateCurrentDatabaseDescription(): void
    {
        /** @var array<string> */
        $tableNames = $this->connection->fetchFirstColumn('SHOW TABLES;');

        $currentDatabaseDescription = [];

        foreach ($tableNames as $tableName) {
            $sql = 'DESCRIBE `'.$tableName.'`;';
            $sqlDescriptionEntries = $this->connection->fetchAllAssociative($sql);

            $newEntry = ['columns' => []];
            $newEntry['tableName'] = $tableName;

            // constraints related to this table
            $sql = 'SELECT *
                        FROM information_schema.REFERENTIAL_CONSTRAINTS
                        WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ?';
            $constraintInfos = $this->connection->fetchAllAssociative($sql, [$this->database, $tableName]);

            var_dump($constraintInfos);

            foreach ($sqlDescriptionEntries as $entry) {
                $newColumn = [];

                // name
                $newColumn['name'] = $entry['Field'];

                // type
                $newColumn['type'] = $entry['Type'];

                // canBeNull
                $newColumn['canBeNull'] = 'YES' == $entry['Null'];

                // defaultValue
                $newColumn['defaultValue'] = $entry['Default'];

                $newEntry['columns'] = $newColumn;
            }
        }

        $this->result['currentDatabaseDescription'] = $currentDatabaseDescription;
    }
}

/*
knowledge stand bauen => CHECK
aktuellen Stand bauen
Stände vergleichen
Diff-SQL generieren

DECIMAL(4,2) noch im Knowledge abbilden
*/
