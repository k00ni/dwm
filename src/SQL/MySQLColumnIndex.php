<?php

declare(strict_types=1);

namespace DWM\SQL;

/**
 * @todo support Seq_in_index = multi column indexes
 */
class MySQLColumnIndex
{
    private string $name;
    private string $columnName;
    private bool $isUnique;
    private string $indexType;

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

    public function getIsUnique(): bool
    {
        return $this->isUnique;
    }

    public function setIsUnique(bool $isUnique): void
    {
        $this->isUnique = $isUnique;
    }

    public function getIndexType(): string
    {
        return $this->indexType;
    }

    public function setIndexType(string $indexType): void
    {
        $this->indexType = $indexType;
    }

    public function differsFrom(?self $otherIndex): bool
    {
        if (null == $otherIndex) {
            return true;
        }

        if ($this->name != $otherIndex->getName()) {
            return true;
        }

        if ($this->columnName != $otherIndex->getColumnName()) {
            return true;
        }

        if ($this->isUnique != $otherIndex->getIsUnique()) {
            return true;
        }

        if ($this->indexType != $otherIndex->getIndexType()) {
            return true;
        }

        return false;
    }

    public function toAddStatement(string $table): string
    {
        $result = '';

        if ($this->isUnique) {
            $result = 'CREATE UNIQUE INDEX `'.$this->name.'` ON '.$table.'(`'.$this->columnName.'`);';
        } else {
            $result = 'ALTER TABLE `'.$table.'` ADD';

            if ('FULLTEXT' == $this->indexType) {
                $result .= ' FULLTEXT';
            }

            $result .= ' KEY `'.$this->name.'` (`'.$this->columnName.'`);';
        }

        return $result;
    }

    public function toDropStatement(string $tableName): string
    {
        return 'ALTER TABLE `'.$tableName.'` DROP INDEX `'.$this->name.'`;';
    }
}
