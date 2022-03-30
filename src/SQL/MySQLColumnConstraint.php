<?php

declare(strict_types=1);

namespace DWM\SQL;

class MySQLColumnConstraint
{
    private string $name;
    private string $columnName;
    private string $referencedTable;
    private string $referencedTableColumnName;
    private string $updateRule;
    private string $deleteRule;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getColumnName(): string
    {
        return $this->columnName;
    }

    public function setColumnName(string $columnName): void
    {
        $this->columnName = $columnName;
    }

    public function getReferencedTable(): string
    {
        return $this->referencedTable;
    }

    public function setReferencedTable(string $referencedTable): void
    {
        $this->referencedTable = $referencedTable;
    }

    public function getReferencedTableColumnName(): string
    {
        return $this->referencedTableColumnName;
    }

    public function setReferencedTableColumnName(string $referencedTableColumnName): void
    {
        $this->referencedTableColumnName = $referencedTableColumnName;
    }

    public function getUpdateRule(): string
    {
        return $this->updateRule;
    }

    public function setUpdateRule(string $updateRule): void
    {
        $this->updateRule = $updateRule;
    }

    public function getDeleteRule(): string
    {
        return $this->deleteRule;
    }

    public function setDeleteRule(string $deleteRule): void
    {
        $this->deleteRule = $deleteRule;
    }

    public function differsFrom(?self $otherConstraint): bool
    {
        if (null == $otherConstraint) {
            return true;
        }

        if ($this->name != $otherConstraint->getName()) {
            return true;
        }

        if ($this->columnName != $otherConstraint->getColumnName()) {
            return true;
        }

        if ($this->referencedTable != $otherConstraint->getReferencedTable()) {
            return true;
        }

        if ($this->referencedTableColumnName != $otherConstraint->getReferencedTableColumnName()) {
            return true;
        }

        if ($this->updateRule != $otherConstraint->getUpdateRule()) {
            return true;
        }

        if ($this->deleteRule != $otherConstraint->getDeleteRule()) {
            return true;
        }

        return false;
    }

    public function toAddStatement(string $tableName): string
    {
        $result = 'ALTER TABLE `'.$tableName.'`';
        $result .= 'ADD CONSTRAINT `'.$this->name.'`';
        $result .= ' FOREIGN KEY (`'.$this->columnName.'`)';
        $result .= ' REFERENCES `'.$this->referencedTable.'` (`'.$this->referencedTableColumnName.'`)';

        if (null != $this->deleteRule) {
            $result .= ' ON DELETE '.$this->deleteRule;
        }

        $result .= ';';

        return $result;
    }

    public function toDropStatement(string $tableName): string
    {
        $result = 'ALTER TABLE `'.$tableName.'`';
        $result .= 'DROP FOREIGN KEY `'.$this->columnName.'`;';

        return $result;
    }
}
