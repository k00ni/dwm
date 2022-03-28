<?php

declare(strict_types=1);

namespace DWM\Process;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use DWM\Attribute\ProcessStep;
use DWM\DWMConfig;
use DWM\RDF\RDFGraph;
use DWM\SimpleStructure\Process;
use Exception;

class TestArrayBasedQueryManager extends Process
{
    private Connection $connection;

    private string $currentPath;

    private DWMConfig $dwmConfig;

    private RDFGraph $graph;

    public function __construct(DWMConfig $dwmConfig)
    {
        parent::__construct();

        $cwd = getcwd();
        if (is_string($cwd)) {
            $this->currentPath = $cwd;
        } else {
            throw new Exception('getcwd() return false!');
        }

        $this->addStep('loadDwmJson');

        $this->addStep('createGraphBasedOnMergedKnowledge');

        $this->addStep('run');

        $this->dwmConfig = $dwmConfig;
    }

    #[ProcessStep()]
    protected function loadDwmJson(): void
    {
        $this->dwmConfig->load($this->currentPath);

        if (is_string($this->dwmConfig->getFileWithDatabaseAccessData())) {
            $accessData = file_get_contents($this->dwmConfig->getFileWithDatabaseAccessData());

            if (is_string($accessData)) {
                /** @var array<string,string> */
                $accessDataArr = json_decode($accessData, true);
                $accessDataArr['driver'] = 'pdo_mysql';

                $this->database = $accessDataArr['dbname'];

                // DB access
                $this->connection = DriverManager::getConnection($accessDataArr);
            } else {
                throw new Exception('Could not open access data file: '.$this->dwmConfig->getFileWithDatabaseAccessData());
            }
        } else {
            throw new Exception('No information found about database access file in dwm.json.');
        }
    }

    #[ProcessStep()]
    protected function createGraphBasedOnMergedKnowledge(): void
    {
        $mergedFilePath = $this->dwmConfig->getMergedKnowledgeJsonLDFilePath();

        if (true === is_string($mergedFilePath) && file_exists($mergedFilePath)) {
            $content = file_get_contents($mergedFilePath);
            if (is_string($content)) {
                $jsonArr = json_decode($content, true);
                if (is_array($jsonArr)) {
                    $this->graph = new RDFGraph($jsonArr);
                } else {
                    throw new Exception('Decoded JSON is not an array.');
                }
            } else {
                throw new Exception('Could not read content of '.$mergedFilePath);
            }
        } else {
            throw new Exception('Merged knowledge file doesn not exist: '.$mergedFilePath);
        }
    }

    /**
     * @param array<string,string> $propertyValues
     *
     * @return array<array<mixed>>
     */
    private function fetchByPropertyValues(string $tableName, array $propertyValues): array
    {
        $sql = 'SELECT * FROM `'.$tableName.'` WHERE ';
        $params = [];
        $wheres = [];

        foreach ($propertyValues as $key => $value) {
            $wheres[] = $key .' = ?';
            $params[] = $value;
        }

        $sql .= implode(' AND ', $wheres);

        $entries = (array) $this->connection->fetchAllAssociative($sql, $params);

        $this->checkIfEntriesAreValid($tableName, $entries);

        return $entries;
    }

    /**
     * @param array<array<mixed>> $entries
     */
    private function checkIfEntriesAreValid(string $tableName, array $entries): void
    {
        // TODO remove if table names are always starting with uppercase letter
        $className = ucfirst($tableName);

        // load class related to table name
        $subGraph = $this->graph->getSubGraphWithEntriesWithPropertyValue('dwm:className', $className);

        if (0 < count($subGraph)) {
            $classID = ...

            // load related nodeshape
            ...

        } else {
            throw new Exception('No class information found about '.$className);
        }
    }

    #[ProcessStep()]
    protected function run(): void
    {
        $orders = $this->fetchByPropertyValues('order', ['sales_channel_id' => 48]);

        // var_dump($orders);
    }
}
