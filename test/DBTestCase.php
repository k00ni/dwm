<?php

declare(strict_types=1);

namespace DWM\Test;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

class DBTestCase extends TestCase
{
    protected Connection $connection;
    protected string $database;

    public function setUp(): void
    {
        parent::setUp();

        $accessDataArr = [
            'dbname' => $_ENV['DB_NAME'],
            'user' => $_ENV['DB_USER'],
            'password' => $_ENV['DB_PASS'],
            'host' => $_ENV['DB_HOST'],
            'driver' => 'pdo_mysql',
        ];

        $this->database = $_ENV['DB_NAME'];

        $this->connection = DriverManager::getConnection($accessDataArr);

        // remove all tables
        $tables = $this->connection->fetchFirstColumn('SHOW TABLES');
        $this->connection->executeQuery('SET FOREIGN_KEY_CHECKS=0;');
        foreach ($tables as $table) {
            $this->connection->executeQuery('DROP TABLE `'.$table.'`');
        }
        $this->connection->executeQuery('SET FOREIGN_KEY_CHECKS=1;');
    }
}
