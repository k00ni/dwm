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

        $table2 = new MySQLTable($this->connection, $this->database);
        $table2->setName('User');

        $result = $userTable->checkDiffAndCreateSQLStatements($table2);

        $this->assertEquals(
            [
                'alterTableStatements' => [
                    'ALTER TABLE `User` ADD `id` int(11) AUTO_INCREMENT NOT NULL;',
                    'ALTER TABLE `User` ADD `some_id` int(11) NOT NULL;',
                    'ALTER TABLE `User` ADD `some_varchar` varchar(100) DEFAULT "default 1" NOT NULL;',
                    'ALTER TABLE `User` ADD `some_smallint` smallint(6) DEFAULT "1" NOT NULL;',
                    'ALTER TABLE `User` ADD `some_decimal` decimal(4,2) DEFAULT "1.00" NOT NULL;',
                    'ALTER TABLE `User` ADD `some_text` text NULL;',
                    'ALTER TABLE `User` ADD `address_id` int(11) NOT NULL;',
                ],
                'addForeignKeyStatements' => [
                    'ALTER TABLE `User`ADD CONSTRAINT `CONSTRAINT1` FOREIGN KEY (`address_id`) REFERENCES `Address` (`id`) ON DELETE CASCADE;',
                ],
                'dropForeignKeyStatements' => [],
                'addPrimaryKeyStatements' => [
                    'ALTER TABLE User ADD PRIMARY KEY(id);',
                ],
                'dropPrimaryKeyStatements' => [],
            ],
            $result
        );
    }
}
