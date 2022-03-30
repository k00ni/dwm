<?php

declare(strict_types=1);

namespace DWM\SQL;

use Doctrine\DBAL\Connection;
use Exception;

class MySQLTable
{
    private Connection $connection;
    private string $database;

    private string $name = '';

    /**
     * @var MySQLColumn[]
     */
    private array $columns = [];

    private string $engine = 'InnoDB';

    private string $charset = 'utf8mb4';

    private string $collate = 'utf8mb4_unicode_520_ci';

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

    public function addColumn(MySQLColumn $column)
    {
        if (isset($this->columns[$column->getName()])) {
            throw new Exception('Table already has a column with name '.$column->getName());
        } else {
            $this->columns[$column->getName()] = $column;
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
            $newColumn = new MySQLColumn();
            $newColumn->setName($entry['Field']);
            $newColumn->setType($entry['Type']);
            $newColumn->setCanBeNull('YES' == $entry['Null']);
            $newColumn->setIsPrimaryKey('PRI' == $entry['Key']);
            $newColumn->setIsAutoIncrement('auto_increment' == $entry['Extra']);

            // defaultValue
            if (null !== $entry['Default']) {
                $newColumn->setDefaultValue($entry['Default']);
            }

            $this->columns[$newColumn->getName()] = $newColumn;
        }
    }

    /**
     * For each existing column constraint information will be loaded, if available.
     */
    public function loadConstraintInformationPerColumn(): void
    {
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
                $info2 = $this->connection->fetchAssociative($sql, [$this->database, $info['CONSTRAINT_NAME']]);

                $constraint->setUpdateRule($info2['UPDATE_RULE']);
                $constraint->setDeleteRule($info2['DELETE_RULE']);

                $this->columns[$info['COLUMN_NAME']]->setConstraint($constraint);
            } else {
                throw new Exception('Setup column '.$info['COLUMN_NAME'].' before load constraint information.');
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

    public function checkDiffAndCreateSQLStatements(self $otherTable): array
    {
        $report = [
            'alterTableStatements' => [],
            'addForeignKeyStatements' => [],
            'dropForeignKeyStatements' => [],
            'addPrimaryKeyStatements' => [],
            'dropPrimaryKeyStatements' => [],
        ];

        foreach ($this->columns as $columnName => $column) {
            // column exists
            if ($otherTable->hasColumnWithName($columnName)) {
                $otherColumn = $otherTable->getColumnWithName($columnName);
                $columnSqls = $column->checkDiffAndCreateSQLStatements($this->name, $otherColumn);

                foreach ([
                    'alterTableStatement',
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
                $report['alterTableStatements'][] = $column->toAlterTable($this->name, 'ADD');

                if ($column->getIsPrimaryKey()) {
                    $stmt = 'ALTER TABLE '.$this->name.' ADD PRIMARY KEY';
                    $stmt .= '('.$column->getName().');';
                    $report['addPrimaryKeyStatements'][] = $stmt;
                }

                if ($column->getConstraint() instanceof MySQLColumnConstraint) {
                    $report['addForeignKeyStatements'][] = $column->getConstraint()->toAddStatement($this->name);
                }
            }
        }

        return $report;
    }

    public function toCreateStatement(): string
    {
        $result = 'CREATE TABLE '.$this->name.' (';

        $lines = [];
        foreach ($this->columns as $column) {
            $lines[] = $column->toLine();
        }
        $result .= implode(PHP_EOL, $lines);

        $result .= ') ENGINE='.$this->engine;
        $result .= ' CHARSET='.$this->charset;
        $result .= ' COLLATE='.$this->collate;
        $result .= ';';

        return $result;
    }
}
