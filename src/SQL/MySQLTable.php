<?php

declare(strict_types=1);

namespace DWM\SQL;

use Doctrine\DBAL\Connection;
use Exception;

class MySQLTable
{
    private Connection $connection;

    /**
     * @var MySQLColumn[]
     */
    private array $columns = [];

    private string $database;

    private string $charset = 'utf8mb4';

    private string $collate = 'utf8mb4_unicode_520_ci';

    private string $engine = 'InnoDB';

    private string $name = '';

    /**
     * @var MySQLColumnIndex[]
     */
    private array $indexes = [];

    public function __construct(Connection $connection, string $database)
    {
        $this->connection = $connection;
        $this->database = $database;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function addColumn(MySQLColumn $column): void
    {
        if (isset($this->columns[$column->getName()])) {
            throw new Exception('Table already has a column with name '.$column->getName());
        } else {
            $this->columns[$column->getName()] = $column;
        }
    }

    public function addIndex(MySQLColumnIndex $index): void
    {
        if (isset($this->indexes[$index->getName()])) {
            throw new Exception('Table already has an index with name '.$index->getName());
        } else {
            $this->indexes[$index->getName()] = $index;
        }
    }

    public function loadColumnsFromDB(): void
    {
        if (0 == strlen($this->name)) {
            throw new Exception('Table name is not set.');
        }

        // describe table columns
        $sqlDescriptionEntries = $this->connection->fetchAllAssociative('DESCRIBE `'.$this->name.'`;');

        foreach ($sqlDescriptionEntries as $entry) {
            /** @var array<string,string|null> */
            $entry = $entry;
            $newColumn = new MySQLColumn();
            $newColumn->setName((string) $entry['Field']);

            // type
            $type = (string) preg_replace('/(\([0-9]+\))/', '', (string) $entry['Type']);
            $newColumn->setType($type);

            // length
            preg_match('/\(([0-9]+)\)/', (string) $entry['Type'], $match);
            if (isset($match[1])) {
                $newColumn->setLength($match[1]);
            }

            $newColumn->setCanBeNull('YES' == $entry['Null']);
            $newColumn->setIsPrimaryKey('PRI' == $entry['Key']);
            $newColumn->setIsAutoIncrement('auto_increment' == $entry['Extra']);

            // defaultValue
            if (null !== $entry['Default']) {
                $newColumn->setDefaultValue($entry['Default']);
            }

            $this->addColumn($newColumn);
        }
    }

    /**
     * For each existing column constraint information will be loaded, if available.
     */
    public function loadConstraintInformationPerColumn(): void
    {
        if (0 == strlen($this->name)) {
            throw new Exception('Table name is not set.');
        }

        // foreign key
        $sql = 'SELECT *
                  FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                 WHERE REFERENCED_TABLE_SCHEMA = ? AND TABLE_NAME = ?;';
        $foreignKeyInfos = $this->connection->fetchAllAssociative(
            $sql,
            [$this->database, $this->name]
        );

        foreach ($foreignKeyInfos as $info) {
            /** @var array<string,string> */
            $info = $info;

            if (isset($this->columns[$info['COLUMN_NAME']])) {
                $constraint = new MySQLColumnConstraint();

                $constraint->setName($info['CONSTRAINT_NAME']);
                $constraint->setColumnName($info['COLUMN_NAME']);
                $constraint->setReferencedTable($info['REFERENCED_TABLE_NAME']);
                $constraint->setReferencedTableColumnName($info['REFERENCED_COLUMN_NAME']);

                // load further constraint information
                $sql = 'SELECT *
                          FROM information_schema.REFERENTIAL_CONSTRAINTS
                         WHERE CONSTRAINT_SCHEMA = ? AND CONSTRAINT_NAME = ?';
                /** @var array<string,string> */
                $info2 = $this->connection->fetchAssociative($sql, [$this->database, $info['CONSTRAINT_NAME']]);

                $constraint->setUpdateRule($info2['UPDATE_RULE']);
                $constraint->setDeleteRule($info2['DELETE_RULE']);

                $this->columns[$info['COLUMN_NAME']]->setConstraint($constraint);
            } else {
                throw new Exception('Setup column '.$info['COLUMN_NAME'].' before load constraint information.');
            }
        }
    }

    public function loadIndexInformation(): void
    {
        if (0 == strlen($this->name)) {
            throw new Exception('Table name is not set.');
        }

        $indexInfoArr = $this->connection->fetchAllAssociative('SHOW INDEXES FROM `'.$this->name.'`');

        foreach ($indexInfoArr as $indexInfo) {
            /** @var array<string,string> */
            $indexInfo = $indexInfo;
            if (isset($this->columns[$indexInfo['Column_name']])) {
                $index = new MySQLColumnIndex();
                $index->setName($indexInfo['Key_name']);
                $index->setColumnName($indexInfo['Column_name']);
                $index->setIsUnique('0' == $indexInfo['Non_unique']);
                $index->setIndexType($indexInfo['Index_type']);

                $this->addIndex($index);
            } else {
                throw new Exception('Setup column '.$indexInfo['Column_name'].' before load constraint information.');
            }
        }
    }

    public function hasColumnWithName(string $name): bool
    {
        return true === isset($this->columns[$name]);
    }

    public function getColumnWithName(string $name): MySQLColumn
    {
        if ($this->hasColumnWithName($name)) {
            return $this->columns[$name];
        }

        throw new Exception('Table does not have a column with name '.$name);
    }

    public function hasIndexWithName(string $name): bool
    {
        return true === isset($this->indexes[$name]);
    }

    public function getIndexWithName(string $name): MySQLColumnIndex
    {
        if ($this->hasColumnWithName($name)) {
            return $this->indexes[$name];
        }

        throw new Exception('Table does not have an index with name '.$name);
    }

    /**
     * @return array<string,array<mixed>>
     */
    public function checkDiffAndCreateSQLStatements(self $otherTable): array
    {
        $report = [
            'alterTableStatements' => [],
            'addForeignKeyStatements' => [],
            'dropForeignKeyStatements' => [],
            'addPrimaryKeyStatements' => [],
            'dropPrimaryKeyStatements' => [],
            'addKeyStatements' => [],
            'dropKeyStatements' => [],
        ];

        /*
         * columns
         */
        foreach ($this->columns as $columnName => $column) {
            // column exists
            if ($otherTable->hasColumnWithName($columnName)) {
                $otherColumn = $otherTable->getColumnWithName($columnName);
                /** @var array<string,array<string>> */
                $columnSqls = $column->checkDiffAndCreateSQLStatements($this->name, $otherColumn);

                $report['alterTableStatements'] = array_merge($report['alterTableStatements'], $columnSqls['alterTableStatements']);

                foreach ([
                    'addForeignKeyStatement',
                    'dropForeignKeyStatement',
                    'addPrimaryKeyStatement',
                    'dropPrimaryKeyStatement',
                ] as $key) {
                    if (null != $columnSqls[$key]) {
                        $report[$key.'s'][] = $columnSqls[$key];
                    }
                }
            } else {
                // column does not exist
                $stmts = $column->getAlterTableStatements($this->name, 'ADD');

                $report['alterTableStatements'][] = $stmts['alterTableForColumn'];
                if (null !== $stmts['alterTableForAutoIncrement']) {
                    $report['alterTableStatements'][] = $stmts['alterTableForAutoIncrement'];
                }

                if (true == $column->getIsPrimaryKey()) {
                    $stmt = 'ALTER TABLE '.$this->name.' ADD PRIMARY KEY('.$column->getName().');';
                    $report['addPrimaryKeyStatements'][] = $stmt;
                }

                if ($column->getConstraint() instanceof MySQLColumnConstraint) {
                    $report['addForeignKeyStatements'][] = $column->getConstraint()->toAddStatement($this->name);
                }
            }
        }

        /*
         * indexes
         */
        foreach ($this->indexes as $indexName => $index) {
            // index exists
            if ($otherTable->hasIndexWithName($indexName)) {
                $otherIndex = $otherTable->getIndexWithName($indexName);
                if ($index->differsFrom($otherIndex)) {
                    $report['dropKeyStatements'][] = $index->toDropStatement($this->name);
                    $report['addKeyStatements'][] = $index->toAddStatement($this->name);
                }
            } else {
                // index does not exist
                $report['addKeyStatements'][] = $index->toAddStatement($this->name);
            }
        }

        return $report;
    }

    public function toCreateStatement(): string
    {
        $result = 'CREATE TABLE `'.$this->name.'` ('.PHP_EOL;

        /** @var array<string> */
        $lines = [];
        foreach ($this->columns as $column) {
            $lines[] = '    '.$column->toLine().',';
        }
        $result .= implode(PHP_EOL, $lines);

        // remove last ,
        $result = substr($result, 0, strlen($result) - 1);

        $result .= PHP_EOL.') ENGINE='.$this->engine;
        $result .= ' CHARSET='.$this->charset;
        $result .= ' COLLATE='.$this->collate;
        $result .= ';';

        return $result;
    }

    /**
     * @return array<string>
     */
    public function getStatementsToAddAllConstraints(): array
    {
        $result = [];

        foreach ($this->columns as $column) {
            if ($column->getConstraint() instanceof MySQLColumnConstraint) {
                $result[] = $column->getConstraint()->toAddStatement($this->name);
            }
        }

        return $result;
    }

    /**
     * @return array<string>
     */
    public function getStatementsToAddAllIndexes(): array
    {
        $result = [];

        foreach ($this->indexes as $name => $index) {
            $result[] = $index->toAddStatement($this->name);
        }

        return $result;
    }
}
