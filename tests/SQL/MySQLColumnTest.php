<?php

declare(strict_types=1);

namespace DWM\Tests\SQL;

use DWM\SQL\MySQLColumn;
use DWM\SQL\MySQLColumnConstraint;
use DWM\Test\DBTestCase;

class MySQLColumnTest extends DBTestCase
{
    public function testToLine(): void
    {
        // col1
        $col1 = new MySQLColumn();
        $col1->setName('col1');
        $col1->setType('int');
        $col1->setLength('10');
        $col1->setIsPrimaryKey(true);
        $col1->setIsAutoIncrement(true);
        $col1->setDefaultValue('dv1');

        self::assertEquals(
            '`col1` int(10) DEFAULT "dv1" NOT NULL',
            $col1->toLine()
        );
    }

    public function testDiffTwoColumns(): void
    {
        // col1
        $col1 = new MySQLColumn();
        $col1->setName('col1');
        $col1->setType('int');
        $col1->setLength('10');
        $col1->setIsPrimaryKey(true);
        $col1->setIsAutoIncrement(true);
        $col1->setDefaultValue('dv1');

        // col1 > constraint
        $constraint = new MySQLColumnConstraint();
        $constraint->setName('constraint1');
        $constraint->setColumnName('col1');
        $constraint->setReferencedTable('refTable');
        $constraint->setReferencedTableColumnName('refColumn');
        $constraint->setUpdateRule('CASCADE');
        $constraint->setDeleteRule('CASCADE');
        $col1->setConstraint($constraint);

        // col2
        $col2 = new MySQLColumn();
        $col2->setName('col1');

        $result = $col1->checkDiffAndCreateSQLStatements('t1', $col2);

        self::assertEquals(
            [
                'alterTableStatements' => [
                    'ALTER TABLE `t1` CHANGE `col1` `col1` int(10) DEFAULT "dv1" NOT NULL;',
                    'ALTER TABLE `t1` MODIFY `col1` int(10) DEFAULT "dv1" NOT NULL AUTO_INCREMENT;',
                ],
                'addPrimaryKeyStatement' => 'ALTER TABLE t1 ADD PRIMARY KEY(col1);',
                'dropPrimaryKeyStatement' => null,
                'addForeignKeyStatement' => 'ALTER TABLE `t1` ADD CONSTRAINT `constraint1` FOREIGN KEY (`col1`) REFERENCES `refTable` (`refColumn`) ON UPDATE CASCADE ON DELETE CASCADE;',
                'dropForeignKeyStatement' => null,
            ],
            $result
        );
    }

    public function testDiffNoForeignKeyInOriginal(): void
    {
        // col1
        $col1 = new MySQLColumn();
        $col1->setName('col1');
        $col1->setType('int');
        $col1->setLength('10');

        // col2
        $col2 = new MySQLColumn();
        $col2->setName('col1');

        // col1 > constraint
        $constraint = new MySQLColumnConstraint();
        $constraint->setName('constraint1');
        $constraint->setColumnName('col1');
        $constraint->setReferencedTable('refTable');
        $constraint->setReferencedTableColumnName('refColumn');
        $constraint->setUpdateRule('CASCADE');
        $constraint->setDeleteRule('CASCADE');
        $col2->setConstraint($constraint);

        $result = $col1->checkDiffAndCreateSQLStatements('t1', $col2);

        self::assertEquals(
            [
                'alterTableStatements' => [
                    'ALTER TABLE `t1` CHANGE `col1` `col1` int(10) NOT NULL;',
                ],
                'addPrimaryKeyStatement' => null,
                'dropPrimaryKeyStatement' => null,
                'addForeignKeyStatement' => 'ALTER TABLE `t1` ADD CONSTRAINT `constraint1` FOREIGN KEY (`col1`) REFERENCES `refTable` (`refColumn`) ON UPDATE CASCADE ON DELETE CASCADE;',
                'dropForeignKeyStatement' => null,
            ],
            $result
        );
    }

    public function testDiffNoPrimaryKeyInOriginal(): void
    {
        // col1
        $col1 = new MySQLColumn();
        $col1->setName('col1');
        $col1->setType('int');
        $col1->setLength('10');
        $col1->setIsPrimaryKey(false);
        $col1->setIsAutoIncrement(false);

        // col2
        $col2 = new MySQLColumn();
        $col2->setName('col1');
        $col2->setIsPrimaryKey(true);
        $col2->setIsAutoIncrement(true);

        $result = $col1->checkDiffAndCreateSQLStatements('t1', $col2);

        self::assertEquals(
            [
                'alterTableStatements' => [
                    'ALTER TABLE `t1` CHANGE `col1` `col1` int(10) NOT NULL;',
                ],
                'addPrimaryKeyStatement' => null,
                'dropPrimaryKeyStatement' => 'ALTER TABLE t1 DROP PRIMARY KEY;',
                'addForeignKeyStatement' => null,
                'dropForeignKeyStatement' => null,
            ],
            $result
        );
    }
}
