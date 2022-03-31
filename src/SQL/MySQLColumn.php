<?php

declare(strict_types=1);

namespace DWM\SQL;

use Exception;

class MySQLColumn
{
    private ?string $name = null;
    private ?string $type = null;
    private ?bool $canBeNull = null;
    private ?bool $isPrimaryKey = null;
    private ?bool $isAutoIncrement = null;
    private ?string $length = null;
    private ?string $defaultValue = null;
    private ?MySQLColumnConstraint $constraint = null;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getIsPrimaryKey(): ?bool
    {
        return $this->isPrimaryKey;
    }

    public function setIsPrimaryKey(bool $isPrimaryKey): void
    {
        $this->isPrimaryKey = $isPrimaryKey;
    }

    public function getIsAutoIncrement(): ?bool
    {
        return $this->isAutoIncrement;
    }

    public function setIsAutoIncrement(bool $isAutoIncrement): void
    {
        $this->isAutoIncrement = $isAutoIncrement;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getLength(): ?string
    {
        return $this->length;
    }

    public function setLength(string $length): void
    {
        $this->length = $length;
    }

    public function getCanBeNull(): ?bool
    {
        return $this->canBeNull;
    }

    public function setCanBeNull(bool $canBeNull): void
    {
        $this->canBeNull = $canBeNull;
    }

    public function getDefaultValue(): ?string
    {
        return $this->defaultValue;
    }

    public function setDefaultValue(string $defaultValue): void
    {
        $this->defaultValue = $defaultValue;
    }

    public function setConstraint(MySQLColumnConstraint $constraint): void
    {
        $this->constraint = $constraint;
    }

    public function getConstraint(): ?MySQLColumnConstraint
    {
        return $this->constraint;
    }

    /**
     * Assumption: This column is meant to be persist and $otherColumn is outdated.
     *
     * @return array<string,array<int,mixed>|string|null>
     */
    public function checkDiffAndCreateSQLStatements(string $table, self $otherColumn): array
    {
        if ($this->name != $otherColumn->getName()) {
            throw new Exception('Column names do not match: '.$this->name.' vs. '.$otherColumn->getName());
        }

        $statements = [
            'alterTableStatements' => [],
            'addForeignKeyStatement' => null,
            'dropForeignKeyStatement' => null,
            'addPrimaryKeyStatement' => null,
            'dropPrimaryKeyStatement' => null,
        ];

        $foundDiff = false;

        if ($this->type != $otherColumn->getType()) {
            $foundDiff = true;
        }

        if ($this->length != $otherColumn->getLength()) {
            $foundDiff = true;
        }

        if ($this->defaultValue != $otherColumn->getDefaultValue()) {
            $foundDiff = true;
        }

        if ($this->canBeNull != $otherColumn->getCanBeNull()) {
            $foundDiff = true;
        }

        if ($this->isAutoIncrement != $otherColumn->getIsAutoIncrement()) {
            $foundDiff = true;
        }

        if ($foundDiff) {
            $stmts = $this->getAlterTableStatements($table, 'CHANGE');
            $statements['alterTableStatements'][] = $stmts['alterTableForColumn'];
            if (null !== $stmts['alterTableForAutoIncrement']) {
                $statements['alterTableStatements'][] = $stmts['alterTableForAutoIncrement'];
            }
        }

        /*
         * primary key
         */
        if (true == $this->isPrimaryKey && false == $otherColumn->getIsPrimaryKey()) {
            $statements['addPrimaryKeyStatement'] = 'ALTER TABLE '.$table.' ADD PRIMARY KEY';
            $statements['addPrimaryKeyStatement'] .= '('.$this->name.');';
        } elseif (false == $this->isPrimaryKey && true == $otherColumn->getIsPrimaryKey()) {
            $statements['dropPrimaryKeyStatement'] = 'ALTER TABLE '.$table.' DROP PRIMARY KEY;';
        }

        /*
         * constraints
         */
        if ($this->constraint instanceof MySQLColumnConstraint) {
            if ($this->constraint->differsFrom($otherColumn->getConstraint())) {
                $statements['addForeignKeyStatement'] = $this->constraint->toAddStatement($table);

                if ($otherColumn->getConstraint() instanceof MySQLColumnConstraint) {
                    $statements['dropForeignKeyStatement'] = $otherColumn->getConstraint()->toDropStatement($table);
                }
            }
        } elseif ($otherColumn->getConstraint() instanceof MySQLColumnConstraint) {
            $statements['addForeignKeyStatement'] = $otherColumn->getConstraint()->toAddStatement($table);
        }

        return $statements;
    }

    public function toLine(): string
    {
        $result = '`'.$this->name.'`';

        $result .= ' '.$this->type;

        // length
        if (null !== $this->length && 0 < strlen($this->length)) {
            $result .= '('.$this->length.')';
        }

        // DEFAULT
        if (null !== $this->defaultValue && 0 < strlen($this->defaultValue)) {
            $result .= ' DEFAULT "'.$this->defaultValue.'"';
        }

        // NULL
        if (true == $this->canBeNull) {
            $result .= ' NULL';
        } else {
            $result .= ' NOT NULL';
        }

        return $result;
    }

    /**
     * @return array<string,string|null>
     */
    public function getAlterTableStatements(string $table, string $type): array
    {
        // all but auto increment
        $result1 = 'ALTER TABLE `'.$table.'` '.$type;
        if ('CHANGE' == $type) {
            $result1 .= ' `'.$this->name.'`';
        }
        $result1 .= ' '.$this->toLine().';';

        // Auto Increment
        $result2 = null;
        if (true == $this->isAutoIncrement) {
            $result2 = 'ALTER TABLE `'.$table.'` MODIFY '.$this->toLine().' AUTO_INCREMENT;';
        }

        return [
            'alterTableForColumn' => $result1,
            'alterTableForAutoIncrement' => $result2,
        ];
    }
}
