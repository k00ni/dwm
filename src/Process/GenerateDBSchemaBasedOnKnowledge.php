<?php

declare(strict_types=1);

namespace DWM\Process;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use DWM\Attribute\ProcessStep;
use DWM\DWMConfig;
use DWM\RDF\RDFGraph;
use DWM\SimpleStructure\Process;
use DWM\SQL\MySQLColumn;
use DWM\SQL\MySQLColumnConstraint;
use DWM\SQL\MySQLTable;
use Exception;

class GenerateDBSchemaBasedOnKnowledge extends Process
{
    private Connection $connection;

    private bool $createFiles;

    private string $currentPath;

    /**
     * @var array<string,MySQLTable>
     */
    private array $currentDatabaseDescription;

    /**
     * @var array<string,MySQLTable>
     */
    private array $expectedDatabaseDescription;

    /**
     * @var array<mixed>
     */
    private array $sqlDiff;

    private string $database;

    private DWMConfig $dwmConfig;

    private RDFGraph $graph;

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

        $this->addStep('generateSQLDiff');

        $this->addStep('createFiles');

        $this->createFiles = $createFiles;
        $this->dwmConfig = $dwmConfig;
    }

    #[ProcessStep()]
    protected function loadDwmJson(): void
    {
        $this->dwmConfig->load($this->currentPath);

        $accessData = $this->dwmConfig->getGenerateKnowledgeBasedOnDatabaseTablesAccessData();

        if (null == $accessData) {
            throw new Exception('No access data found in dwm.json');
        } else {
            $this->connection = DriverManager::getConnection($accessData);
            $this->database = $accessData['dbname'];
        }
    }

    #[ProcessStep()]
    protected function createGraphBasedOnMergedKnowledge(): void
    {
        $mergedFilePath = $this->dwmConfig->getMergedKnowledgeJsonLDFilePath();

        if (true === is_string($mergedFilePath)) {
            $this->graph = new RDFGraph();
            $this->graph->initializeWithMergedKnowledgeJsonLDFile($mergedFilePath);
        } else {
            throw new Exception('Merged knowledge file doesn not exist: '.$mergedFilePath);
        }
    }

    #[ProcessStep()]
    protected function generateExpectedDatabaseDescription(): void
    {
        $subGraph = $this->graph->getSubGraphWithEntriesWithPropertyValue('dwm:isStoredInDatabase', 'true');

        $expectedDatabaseDescription = [];

        foreach ($subGraph->getEntries() as $rdfEntry) {
            /** @var \DWM\RDF\RDFEntry */
            $rdfEntry = $rdfEntry;

            $newTable = new MySQLTable($this->connection, $this->database);

            // table name
            $name = $rdfEntry->getPropertyValue('dwm:className')->getIdOrValue();
            if (null == $name) {
                throw new Exception('No table name found in '.$rdfEntry->getId());
            }
            $newTable->setName($name);

            // get related NodeShape
            $propertyInfo = $this->graph->getPropertyInfoForTargetClassByNodeShape($rdfEntry->getId());

            if (null == $propertyInfo) {
                throw new Exception('No related sh:NodeShape found for '.$rdfEntry->getId());
            }

            foreach ($propertyInfo as $property) {
                /** @var array<string,string> */
                $property = $property;

                $column = new MySQLColumn();

                $column->setName($property['propertyName']);

                // primary key
                if (isset($property['isPrimaryKey'])) {
                    $column->setIsPrimaryKey('true' == $property['isPrimaryKey']);
                    // auto increment
                    if (isset($property['isAutoIncrement'])) {
                        $column->setIsAutoIncrement('true' == $property['isAutoIncrement']);
                    }
                }

                // type
                if (isset($property['mysqlColumnDataType'])) {
                    $columnType = $property['mysqlColumnDataType'];
                    if (isset($property['maxLength'])) {
                        $columnType .= '('.$property['maxLength'].')';
                    } elseif (isset($property['precision']) && isset($property['scale'])) {
                        $columnType .= '('.$property['precision'].','.$property['scale'].')';
                    }
                } else {
                    if ('integer' == $property['datatype']) {
                        $columnType = 'int';

                        if (isset($property['maxLength'])) {
                            $columnType .= '('.$property['maxLength'].')';
                        }
                    } elseif ('string' == $property['datatype']) {
                        $columnType = 'varchar';

                        $maxLength = $property['maxLength'] ?? 255;
                        $columnType .= '('.$maxLength.')';
                    } elseif ('date' == $property['datatype']) {
                        $columnType = 'date';
                    } elseif ('dateTime' == $property['datatype']) {
                        $columnType = 'datetime';
                    } elseif ('double' == $property['datatype']) {
                        $columnType = 'double';
                    } elseif ('float' == $property['datatype']) {
                        $columnType = 'float';
                    } else {
                        throw new Exception('Unknown datatype given: '.$property['datatype']);
                    }
                }
                $column->setType(strtolower($columnType));

                // IS NULL
                $column->setCanBeNull(isset($property['minCount']) && 0 < (int) $property['minCount']);

                // default value
                if (isset($property['defaultValue'])) {
                    $column->setDefaultValue($property['defaultValue']);
                }

                // constraint
                if (isset($property['constraintName'])) {
                    $constraint = new MySQLColumnConstraint();
                    $constraint->setName($property['constraintName']);
                    $constraint->setColumnName($property['constraintColumnName']);
                    $constraint->setReferencedTable($property['constraintReferencedTable']);
                    $constraint->setReferencedTableColumnName($property['constraintReferencedTableColumnName']);
                    $constraint->setUpdateRule($property['constraintUpdateRule']);
                    $constraint->setDeleteRule($property['constraintDeleteRule']);

                    $column->setConstraint($constraint);
                }

                $newTable->addColumn($column);
            }

            $expectedDatabaseDescription[$newTable->getName()] = $newTable;
        }

        $this->expectedDatabaseDescription = $expectedDatabaseDescription;
    }

    #[ProcessStep()]
    protected function generateCurrentDatabaseDescription(): void
    {
        /** @var array<string> */
        $tableNames = $this->connection->fetchFirstColumn('SHOW TABLES;');

        /** @var array<string,MySQLTable> */
        $currentDatabaseDescription = [];

        foreach ($tableNames as $tableName) {
            $table = new MySQLTable($this->connection, $this->database);
            $table->setName($tableName);
            $table->loadColumnsFromDB();
            $table->loadConstraintInformationPerColumn();
            $table->loadIndexInformation();

            $currentDatabaseDescription[$tableName] = $table;
        }

        $this->currentDatabaseDescription = $currentDatabaseDescription;
    }

    #[ProcessStep()]
    protected function generateSQLDiff(): void
    {
        /** @var array<string> */
        $coveredTables = [];

        $this->sqlDiff = [
            'createTables' => [],
            'dropTables' => [],
            'alterTableStatements' => [],
            'addForeignKeyStatements' => [],
            'dropForeignKeyStatements' => [],
            'addPrimaryKeyStatements' => [],
            'dropPrimaryKeyStatements' => [],
            'addKeyStatements' => [],
            'dropKeyStatements' => [],
        ];
        $statementKeys = array_keys($this->sqlDiff);

        // note: expected data counts, what is not in there will be removed or changed
        foreach ($this->expectedDatabaseDescription as $tableName => $expectedTable) {
            if (isset($this->currentDatabaseDescription[$tableName])) {
                $existingTable = $this->currentDatabaseDescription[$tableName];
                $result = $expectedTable->checkDiffAndCreateSQLStatements($existingTable);
                foreach ($statementKeys as $key) {
                    if (isset($result[$key])) {
                        $this->sqlDiff[$key] = array_merge(
                            $this->sqlDiff[$key],
                            $result[$key]
                        );
                    }
                }
            } else {
                // table does not exist yet
                $this->sqlDiff['createTables'][] = $expectedTable->toCreateStatement();
                $this->sqlDiff['addForeignKeyStatements'] = array_merge(
                    $this->sqlDiff['addForeignKeyStatements'],
                    $expectedTable->getStatementsToAddAllConstraints()
                );
                $this->sqlDiff['addKeyStatements'] = array_merge(
                    $this->sqlDiff['addKeyStatements'],
                    $expectedTable->getStatementsToAddAllIndexes()
                );
            }

            $coveredTables[] = $tableName;
        }

        // remove tables which do not exist anymore
        foreach ($this->currentDatabaseDescription as $tableName => $table) {
            if (in_array($tableName, $coveredTables, true)) {
                // OK
            } else {
                $this->sqlDiff['dropTables'][] = 'DROP TABLE `'.$tableName.'`';
            }
        }
    }

    #[ProcessStep()]
    protected function createFiles(): void
    {
        if ($this->createFiles) {
            $sqlMigrationFilePath = $this->dwmConfig->getFolderPathForSqlMigrationFiles();
            $sqlMigrationFilePath .= '/sql-migration-'.date('Y-m-d-H-i-s').'.sql';

            $sqlMigrationFileContent = [];

            /** @var array<string,array<string>> */
            $sqlDiff = $this->sqlDiff;

            /*
             * CREATE
             */
            foreach ([
                'dropKeyStatements',
                'dropForeignKeyStatements',
                'dropPrimaryKeyStatements',
                'createTables',
                'alterTableStatements',
                'addForeignKeyStatements',
                'addPrimaryKeyStatements',
                'addKeyStatements',
                'dropTables',
            ] as $key) {
                foreach ($sqlDiff[$key] as $statement) {
                    $sqlMigrationFileContent[] = $statement;
                }
            }

            // write file
            if (0 < count($sqlMigrationFileContent)) {
                $data = implode(PHP_EOL.PHP_EOL, $sqlMigrationFileContent);
                file_put_contents($sqlMigrationFilePath, $data);
            } else {
                echo 'No changes detected.';
                echo PHP_EOL;
            }
        }
    }
}
