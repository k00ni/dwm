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
            'sqlDiff' => [
                'createTables' => [],
                'alterTables' => [],
                'dropTables' => [],
                'dropPrimaryKeys' => [],
                'addPrimaryKeys' => [],
                'dropForeignKeys' => [],
                'addForeignKeys' => [],
            ],
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
                if (isset($property['mysqlColumnDataType'])) {
                    $column['type'] = $property['mysqlColumnDataType'];
                    if (isset($property['maxLength'])) {
                        $column['type'] .= '('.$property['maxLength'].')';
                    } elseif (isset($property['precision']) && isset($property['scale'])) {
                        $column['type'] .= '('.$property['precision'].','.$property['scale'].')';
                    }
                } else {
                    if ('integer' == $property['datatype']) {
                        $column['type'] = 'int';

                        if (isset($property['maxLength'])) {
                            $column['type'] .= '('.$property['maxLength'].')';
                        }
                    } elseif ('string' == $property['datatype']) {
                        $column['type'] = 'varchar';

                        $maxLength = $property['maxLength'] ?? 255;
                        $column['type'] .= '('.$maxLength.')';
                    } elseif ('date' == $property['datatype']) {
                        $column['type'] = 'date';
                    } elseif ('dateTime' == $property['datatype']) {
                        $column['type'] = 'datetime';
                    } elseif ('double' == $property['datatype']) {
                        $column['type'] = 'double';
                    } elseif ('float' == $property['datatype']) {
                        $column['type'] = 'float';
                    } else {
                        throw new Exception('Unknown datatype given: '.$property['datatype']);
                    }
                }

                $column['type'] = strtolower($column['type']);

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

                $newEntry['columns'][$column['name']] = $column;
            }

            $this->result['expectedDatabaseDescription'][$newEntry['tableName']] = $newEntry;
        }, $subGraph->getEntries());
    }

    /**
     * @return array<string,array<string,string>>
     */
    private function getRefineConstraintInfos(string $tableName): array
    {
        // foreign key
        $sql = 'SELECT *
                  FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                 WHERE REFERENCED_TABLE_SCHEMA = ? AND TABLE_NAME = ?;';
        $foreignKeyInfos = $this->connection->fetchAllAssociative(
            $sql,
            [$this->database, $tableName]
        );

        $result = [];

        foreach ($foreignKeyInfos as $info) {
            /** @var array<string,string> */
            $info = $info;

            // information received will be put on the referenced table
            $newEntry = [
                'constraintName' => $info['CONSTRAINT_NAME'],
                'constraintColumnName' => $info['COLUMN_NAME'],
                'constraintReferencedTable' => $info['REFERENCED_TABLE_NAME'],
                'constraintReferencedTableColumnName' => $info['REFERENCED_COLUMN_NAME'],
            ];

            // load further constraint information
            $sql = 'SELECT *
                      FROM information_schema.REFERENTIAL_CONSTRAINTS
                     WHERE CONSTRAINT_SCHEMA = ? AND CONSTRAINT_NAME = ?';
            $constraint = $this->connection->fetchAssociative($sql, [$this->database, $info['CONSTRAINT_NAME']]);

            $newEntry['constraintUpdateRule'] = $constraint['UPDATE_RULE'];
            $newEntry['constraintDeleteRule'] = $constraint['DELETE_RULE'];

            $result[$info['COLUMN_NAME']] = $newEntry;
        }

        return $result;
    }

    #[ProcessStep()]
    protected function generateCurrentDatabaseDescription(): void
    {
        /** @var array<string> */
        $tableNames = $this->connection->fetchFirstColumn('SHOW TABLES;');

        array_map(function ($tableName) {
            $constraintsPerColumn = $this->getRefineConstraintInfos($tableName);

            // describe table columns
            $sql = 'DESCRIBE `'.$tableName.'`;';
            $sqlDescriptionEntries = $this->connection->fetchAllAssociative($sql);

            $newEntry = ['columns' => []];
            $newEntry['tableName'] = $tableName;

            foreach ($sqlDescriptionEntries as $entry) {
                $newColumn = [];

                // name
                $newColumn['name'] = $entry['Field'];

                // type
                $newColumn['type'] = strtolower($entry['Type']);

                // canBeNull
                $newColumn['canBeNull'] = 'YES' == $entry['Null'];

                // primary key
                if ('PRI' == $entry['Key']) {
                    $newColumn['isPrimaryKey'] = true;
                }

                // auto increment
                if ('auto_increment' == $entry['Extra']) {
                    $newColumn['isAutoIncrement'] = true;
                }

                // defaultValue
                if (null !== $entry['Default']) {
                    $newColumn['defaultValue'] = $entry['Default'];
                }

                if (isset($constraintsPerColumn[$entry['Field']])) {
                    $newColumn['constraint'] = $constraintsPerColumn[$entry['Field']];
                }

                $newEntry['columns'][$newColumn['name']] = $newColumn;

                $newEntry['constraintsPerColumn'] = $constraintsPerColumn;
            }

            $this->result['currentDatabaseDescription'][$tableName] = $newEntry;
        }, $tableNames);
    }

    #[ProcessStep()]
    protected function generateSQLDiff(): void
    {
        $currentDb = $this->result['currentDatabaseDescription'];

        /** @var array<string> */
        $coveredTables = [];

        // expected data counts, what is not in there will be removed or changed
        foreach ($this->result['expectedDatabaseDescription'] as $tableName => $entry) {
            if (isset($currentDb[$tableName])) {
                // column wise check
                foreach ($entry['columns'] as $columnName => $column) {
                    // column exists
                    if (isset($currentDb[$tableName]['columns'][$columnName])) {
                        $columnWithoutConstraintInfo = $column;
                        unset($columnWithoutConstraintInfo['constraint']);

                        $currentDbStateEntry = $currentDb[$tableName]['columns'][$columnName];
                        unset($currentDbStateEntry['constraint']);

                        $diff = array_diff($columnWithoutConstraintInfo, $currentDbStateEntry);

                        // columns differ
                        if (0 < count($diff)) {
                            /*
                             * type of change
                             */
                            if (isset($diff['isPrimaryKey']) && true == $diff['isPrimaryKey']) {
                                $this->result['sqlDiff']['addPrimaryKeys'][] = [
                                    'tableName' => $tableName,
                                    'columnEntry' => $column,
                                ];
                            } else {
                                $this->result['sqlDiff']['alterTables'][] = [
                                    'tableName' => $tableName,
                                    'type' => 'CHANGE',
                                    'columnEntry' => $column,
                                ];
                            }
                        } elseif (
                            false == isset($columnWithoutConstraintInfo['isPrimaryKey'])
                            && isset($currentDbStateEntry['isPrimaryKey'])
                        ) {
                            $this->result['sqlDiff']['dropPrimaryKeys'][] = $tableName;
                        } else {
                            // no difference
                        }
                    } else {
                        // column does not exist
                        $this->result['sqlDiff']['alterTables'][] = [
                            'tableName' => $tableName,
                            'type' => 'ADD',
                            'columnEntry' => $column,
                        ];
                    }
                }
            } else {
                // table does not exist
                $this->result['sqlDiff']['createTables'][] = $entry;
            }

            $coveredTables[] = $tableName;
        }

        // remove tables which do not exist anymore
        foreach ($currentDb as $tableName => $entry) {
            if (in_array($tableName, $coveredTables)) {
                // OK
            } else {
                $this->result['sqlDiff']['dropTables'][] = $tableName;
            }
        }
    }

    private function generateColumnLine(array $column, string $statementType): string
    {
        $columnLine = '    `'.$column['name'].'`';

        if ('CHANGE' == $statementType) {
            $columnLine .= ' `'.$column['name'].'`';
        }

        $columnLine .= ' '.$column['type'];

        // DEFAULT
        if (isset($column['defaultValue'])) {
            $columnLine .= ' DEFAULT "'.$column['defaultValue'].'"';
        }

        // NULL
        if ($column['canBeNull']) {
            $columnLine .= ' NULL';
        } else {
            $columnLine .= ' NOT NULL';
        }

        return $columnLine;
    }

    private function generateAddForeignKeyConstraintLine(string $tableName, array $constraint): string
    {
        $line = 'ALTER TABLE `'.$tableName.'`';
        $line .= ' ADD CONSTRAINT `'.$constraint['name'].'`';

        $line .= ' FOREIGN KEY ('.$constraint['columnName'].')';
        $line .= ' REFERENCES '.$constraint['referencedTable'].' ('.$constraint['referencedTableColumnName'].')';

        if ('RESTRICT' != $constraint['updateRule']) {
            $line .= ' ON UPDATE '.$constraint['deleteRule'];
        }

        if ('RESTRICT' != $constraint['deleteRule']) {
            $line .= ' ON DELETE '.$constraint['deleteRule'];
        }

        $line .= ';';

        return $line;
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
