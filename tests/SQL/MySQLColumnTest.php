<?php

declare(strict_types=1);

namespace DWM\Tests\SQL;

use DWM\SQL\MySQLColumn;
use DWM\SQL\MySQLColumnConstraint;
use DWM\Test\DBTestCase;

class MySQLColumnTest extends DBTestCase
{
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

        $this->assertEquals(
            [
                'alterTableStatement' => 'ALTER TABLE `t1` CHANGE `col1` `col1` int(10) AUTO_INCREMENT DEFAULT "dv1" NOT NULL',
                'addPrimaryKeyStatement' => 'ALTER TABLE t1 ADD PRIMARY KEY(col1);',
                'dropPrimaryKeyStatement' => null,
                'addForeignKeyStatement' => 'ALTER TABLE `t1`ADD CONSTRAINT `constraint1` FOREIGN KEY (`col1`) REFERENCES `refTable` (`refColumn`) ON DELETE CASCADE;',
                'dropForeignKeyStatement' => null,
            ],
            $result
        );
    }
}
