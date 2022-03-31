<?php

declare(strict_types=1);

namespace DWM\Tests\SQL;

use DWM\SQL\MySQLTable;
use DWM\Test\DBTestCase;

class MySQLTableTest extends DBTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        /** @var array<string> */
        $sqls = require $this->rootDir.'/test/data/sqls1.php';
        foreach ($sqls as $sql) {
            $this->connection->executeQuery($sql);
        }
    }

    public function testToCreateStatement(): void
    {
        /*
         * setup test tables
         */
        // User
        $userTable = new MySQLTable($this->connection, $this->database);
        $userTable->setName('User');
        $userTable->loadColumnsFromDB();
        $userTable->loadConstraintInformationPerColumn();
        $userTable->loadIndexInformation();

        self::assertEquals(
            'CREATE TABLE `User` (
    `id` int(11) NOT NULL,
    `some_id` int(11) NOT NULL,
    `some_varchar` varchar(100) DEFAULT "default 1" NOT NULL,
    `some_smallint` smallint(6) DEFAULT "1" NOT NULL,
    `some_decimal` decimal(4,2) DEFAULT "1.00" NOT NULL,
    `some_text` text NULL,
    `address_id` int(11) NOT NULL
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;',
            $userTable->toCreateStatement()
        );
    }

    public function testDiffTwoTables(): void
    {
        /*
         * setup test tables
         */
        // User
        $userTable = new MySQLTable($this->connection, $this->database);
        $userTable->setName('User');
        $userTable->loadColumnsFromDB();
        $userTable->loadConstraintInformationPerColumn();
        $userTable->loadIndexInformation();

        $table2 = new MySQLTable($this->connection, $this->database);
        $table2->setName('User');

        $result = $userTable->checkDiffAndCreateSQLStatements($table2);

        self::assertEquals(
            [
                'alterTableStatements' => [
                    'ALTER TABLE `User` ADD `id` int(11) NOT NULL;',
                    'ALTER TABLE `User` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;',
                    'ALTER TABLE `User` ADD `some_id` int(11) NOT NULL;',
                    'ALTER TABLE `User` ADD `some_varchar` varchar(100) DEFAULT "default 1" NOT NULL;',
                    'ALTER TABLE `User` ADD `some_smallint` smallint(6) DEFAULT "1" NOT NULL;',
                    'ALTER TABLE `User` ADD `some_decimal` decimal(4,2) DEFAULT "1.00" NOT NULL;',
                    'ALTER TABLE `User` ADD `some_text` text NULL;',
                    'ALTER TABLE `User` ADD `address_id` int(11) NOT NULL;',
                ],
                'addForeignKeyStatements' => [
                    'ALTER TABLE `User` ADD CONSTRAINT `CONSTRAINT1` FOREIGN KEY (`address_id`) REFERENCES `Address` (`id`) ON DELETE CASCADE;',
                ],
                'dropForeignKeyStatements' => [],
                'addPrimaryKeyStatements' => [
                    'ALTER TABLE User ADD PRIMARY KEY(id);',
                ],
                'dropPrimaryKeyStatements' => [],
                'addKeyStatements' => [
                    'CREATE UNIQUE INDEX `PRIMARY` ON User(`id`);',
                    'CREATE UNIQUE INDEX `UNIQUE1` ON User(`some_smallint`);',
                    'ALTER TABLE `User` ADD KEY `INDEX1` (`some_id`);',
                    'ALTER TABLE `User` ADD KEY `INDEX2` (`some_varchar`);',
                    'ALTER TABLE `User` ADD KEY `CONSTRAINT1` (`address_id`);',
                    'ALTER TABLE `User` ADD FULLTEXT KEY `some_text` (`some_text`);',
                ],
                'dropKeyStatements' => [],
            ],
            $result
        );
    }

    public function testDiffTwoTables2(): void
    {
        $this->connection->executeQuery('ALTER TABLE User CHANGE `id` `id` INT(11) NOT NULL;');
        $this->connection->executeQuery('ALTER TABLE User DROP PRIMARY KEY;');

        /*
         * setup test tables
         */
        // User
        $userTable = new MySQLTable($this->connection, $this->database);
        $userTable->setName('User');
        $userTable->loadColumnsFromDB();
        $userTable->loadConstraintInformationPerColumn();
        $userTable->loadIndexInformation();

        $table2 = new MySQLTable($this->connection, $this->database);
        $table2->setName('User');

        $result = $userTable->checkDiffAndCreateSQLStatements($table2);

        self::assertEquals(
            [
                'alterTableStatements' => [
                    'ALTER TABLE `User` ADD `id` int(11) NOT NULL;',
                    'ALTER TABLE `User` ADD `some_id` int(11) NOT NULL;',
                    'ALTER TABLE `User` ADD `some_varchar` varchar(100) DEFAULT "default 1" NOT NULL;',
                    'ALTER TABLE `User` ADD `some_smallint` smallint(6) DEFAULT "1" NOT NULL;',
                    'ALTER TABLE `User` ADD `some_decimal` decimal(4,2) DEFAULT "1.00" NOT NULL;',
                    'ALTER TABLE `User` ADD `some_text` text NULL;',
                    'ALTER TABLE `User` ADD `address_id` int(11) NOT NULL;',
                ],
                'addForeignKeyStatements' => [
                    'ALTER TABLE `User` ADD CONSTRAINT `CONSTRAINT1` FOREIGN KEY (`address_id`) REFERENCES `Address` (`id`) ON DELETE CASCADE;',
                ],
                'dropForeignKeyStatements' => [],
                'addPrimaryKeyStatements' => [
                    // note: after removing id as primary key, some_smallint becomes him, because it is UNIQUE
                    'ALTER TABLE User ADD PRIMARY KEY(some_smallint);',
                ],
                'dropPrimaryKeyStatements' => [],
                'addKeyStatements' => [
                    'CREATE UNIQUE INDEX `UNIQUE1` ON User(`some_smallint`);',
                    'ALTER TABLE `User` ADD KEY `INDEX1` (`some_id`);',
                    'ALTER TABLE `User` ADD KEY `INDEX2` (`some_varchar`);',
                    'ALTER TABLE `User` ADD KEY `CONSTRAINT1` (`address_id`);',
                    'ALTER TABLE `User` ADD FULLTEXT KEY `some_text` (`some_text`);',
                ],
                'dropKeyStatements' => [],
            ],
            $result
        );
    }

    public function testGetStatementsToAddAllConstraints(): void
    {
        /*
         * setup test tables
         */
        // User
        $userTable = new MySQLTable($this->connection, $this->database);
        $userTable->setName('User');
        $userTable->loadColumnsFromDB();
        $userTable->loadConstraintInformationPerColumn();
        $userTable->loadIndexInformation();

        self::assertEquals(
            [
                'ALTER TABLE `User` ADD CONSTRAINT `CONSTRAINT1` FOREIGN KEY (`address_id`) REFERENCES `Address` (`id`) ON DELETE CASCADE;',
            ],
            $userTable->getStatementsToAddAllConstraints()
        );
    }

    public function testGetStatementsToAddAllIndexes(): void
    {
        /*
         * setup test tables
         */
        // User
        $userTable = new MySQLTable($this->connection, $this->database);
        $userTable->setName('User');
        $userTable->loadColumnsFromDB();
        $userTable->loadConstraintInformationPerColumn();
        $userTable->loadIndexInformation();

        self::assertEquals(
            [
                'CREATE UNIQUE INDEX `PRIMARY` ON User(`id`);',
                'CREATE UNIQUE INDEX `UNIQUE1` ON User(`some_smallint`);',
                'ALTER TABLE `User` ADD KEY `INDEX1` (`some_id`);',
                'ALTER TABLE `User` ADD KEY `INDEX2` (`some_varchar`);',
                'ALTER TABLE `User` ADD KEY `CONSTRAINT1` (`address_id`);',
                'ALTER TABLE `User` ADD FULLTEXT KEY `some_text` (`some_text`);',
            ],
            $userTable->getStatementsToAddAllIndexes()
        );
    }
}
