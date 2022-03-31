<?php

declare(strict_types=1);

namespace DWM\Tests\SQL;

use DWM\SQL\MySQLColumnIndex;
use DWM\Test\DBTestCase;

class MySQLColumnIndexTest extends DBTestCase
{
    public function testToAddStatement(): void
    {
        // index1
        $index1 = new MySQLColumnIndex();
        $index1->setName('index1');
        $index1->setColumnName('col1');
        $index1->setIndexType('FULLTEXT');
        $index1->setIsUnique(false);

        self::assertEquals(
            'ALTER TABLE `table1` ADD FULLTEXT KEY `index1` (`col1`);',
            $index1->toAddStatement('table1')
        );
    }

    public function testDiff(): void
    {
        // index1
        $index1 = new MySQLColumnIndex();
        $index1->setName('index1');
        $index1->setColumnName('col1');
        $index1->setIndexType('FULLTEXT');
        $index1->setIsUnique(false);

        // index2
        $index2 = new MySQLColumnIndex();
        $index2->setName('index1');
        $index2->setColumnName('col2');
        $index2->setIndexType('FULLTEXT');
        $index2->setIsUnique(false);

        self::assertTrue($index1->differsFrom($index2));

        self::assertEquals(
            'ALTER TABLE `table1` ADD FULLTEXT KEY `index1` (`col1`);',
            $index1->toAddStatement('table1')
        );

        self::assertEquals(
            'ALTER TABLE `table1` DROP INDEX `index1`;',
            $index1->toDropStatement('table1')
        );
    }
}
