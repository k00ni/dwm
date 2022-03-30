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

    private string $currentPath;

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
        $this->result = [
            'currentDatabaseDescription' => [],
            'expectedDatabaseDescription' => [],
        ];
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

        array_map(function ($rdfEntry) {
            /** @var \DWM\RDF\RDFEntry */
            $rdfEntry = $rdfEntry;

            $newTable = new MySQLTable($this->connection, $this->database);

            // table name
            $newTable->setName($rdfEntry->getPropertyValue('dwm:className')->getIdOrValue());

            // get related NodeShape
            $propertyInfo = $this->graph->getPropertyInfoForTargetClassByNodeShape($rdfEntry->getId());
            foreach ($propertyInfo as $property) {
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
                    $constraint->setReferencedTable($property['constraintReferencedTable']);
                    $constraint->setReferencedTableColumnName($property['constraintReferencedTableColumnName']);
                    $constraint->setUpdateRule($property['constraintUpdateRule']);
                    $constraint->setDeleteRule($property['constraintDeleteRule']);

                    $column->setConstraint($constraint);
                }
            }

            $newTable->addColumn($column);

            $this->result['expectedDatabaseDescription'][$newTable->getName()] = $newTable;
        }, $subGraph->getEntries());
    }

    #[ProcessStep()]
    protected function generateCurrentDatabaseDescription(): void
    {
        /** @var array<string> */
        $tableNames = $this->connection->fetchFirstColumn('SHOW TABLES;');

        array_map(function ($tableName) {
            $table = new MySQLTable($this->connection, $this->database);
            $table->loadColumnsFromDB();
            $table->loadConstraintInformationPerColumn();

            $this->result['currentDatabaseDescription'][$tableName] = $table;
        }, $tableNames);
    }

    #[ProcessStep()]
    protected function generateSQLDiff(): void
    {
        /** @var array<string> */
        $coveredTables = [];

        $this->result['sqlDiff'] = [
            'createTables' => [],
            'alterTables' => [],
            'dropTables' => [],
            'dropPrimaryKeys' => [],
            'addPrimaryKeys' => [],
            'dropForeignKeys' => [],
            'addForeignKeys' => [],
        ];

        // expected data counts, what is not in there will be removed or changed
        foreach ($this->result['expectedDatabaseDescription'] as $tableName => $table) {
            if (isset($this->result['currentDatabaseDescription'][$tableName])) {
            } else {
                // table does not exist
                $this->result['sqlDiff']['createTables'][] = $entry;
            }

            $coveredTables[] = $tableName;
        }

        // remove tables which do not exist anymore
        foreach ($this->result['currentDatabaseDescription'] as $tableName => $entry) {
            if (in_array($tableName, $coveredTables)) {
                // OK
            } else {
                $this->result['sqlDiff']['dropTables'][] = $tableName;
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

            /*
             * CREATE
             */
            foreach ($this->result['sqlDiff']['createTables'] as $entry) {
                $constraints = [];
                $line = 'CREATE TABLE `'.$entry['tableName'].'`(';

                $columnLines = [];
                foreach ($entry['columns'] as $column) {
                    $columnLine = $this->generateColumnLine($column, 'ADD');
                    $columnLines[] = $columnLine;

                    if (isset($column['constraint'])) {
                        $constraints[] = $column['constraint'];
                    }
                }
                $line .= implode(','.PHP_EOL, $columnLines);

                $line .= PHP_EOL.') DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_520_ci` ENGINE = InnoDB;';

                $sqlMigrationFileContent[] = $line;

                // add constraints
                foreach ($constraints as $constraint) {
                    $line = $this->generateAddForeignKeyConstraintLine($entry['tableName'], $constraint);
                    $sqlMigrationFileContent[] = $line;
                }
            }

            /*
             * ALTER
             */
            foreach ($this->result['sqlDiff']['alterTables'] as $entry) {
                $line = 'ALTER TABLE `'.$entry['tableName'].'`';

                $line .= ' '.$entry['type'].' '.$this->generateColumnLine($entry['columnEntry'], $entry['type']);

                $sqlMigrationFileContent[] = $line.';';

                // foreign key
                if (isset($entry['columnEntry']['constraint'])) {
                    $line = $this->generateAddForeignKeyConstraintLine($entry['tableName'], $entry['columnEntry']['constraint']);

                    $sqlMigrationFileContent[] = $line;
                }

                // delete outdated foreign keys?
            }

            /*
             * DROP
             */
            foreach ($this->result['sqlDiff']['dropTables'] as $table) {
                $sqlMigrationFileContent[] = 'DROP TABLE `'.$table.'`';
            }

            /*
             * add primary keys
             */
            foreach ($this->result['sqlDiff']['addPrimaryKeys'] as $entry) {
                $line = 'ALTER TABLE `'.$entry['tableName'].'` ADD PRIMARY KEY (`'.$entry['columnEntry']['name'].'`);';
                $sqlMigrationFileContent[] = $line;
            }

            /*
             * drop primary keys
             */
            foreach ($this->result['sqlDiff']['dropPrimaryKeys'] as $tableName) {
                $line = 'ALTER TABLE `'.$tableName.'` DROP PRIMARY KEY;';
                $sqlMigrationFileContent[] = $line;
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
