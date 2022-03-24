<?php

declare(strict_types=1);

namespace DWM\Process;

use DWM\Attribute\ProcessStep;
use DWM\DWMConfig;
use DWM\RDF\RDFGraph;
use DWM\SimpleStructure\Process;
use Exception;

class CreateKnowledgeMaps extends Process
{
    /**
     * @var array<mixed>
     */
    private array $classConfig;

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

        $this->addStep('getMetaDataOfDBRelatedClasses');

        $this->addStep('createMapForDBRelatedClasses');

        $this->addStep('createMapForProcesses');

        $this->dwmConfig = $dwmConfig;
    }

    #[ProcessStep()]
    protected function loadDwmJson(): void
    {
        $this->dwmConfig->load($this->currentPath);
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

    #[ProcessStep()]
    protected function getMetaDataOfDBRelatedClasses(): void
    {
        $subGraph = $this->graph->getSubGraphWithEntriesWithPropertyValue('dwm:isStoredInDatabase', 'true');

        $this->classConfig = array_map(function ($rdfEntry) {
            /** @var array<string,string|array<mixed>> */
            $newEntry = [];

            /** @var \DWM\RDF\RDFEntry */
            $rdfEntry = $rdfEntry;

            // class name
            $newEntry['className'] = $rdfEntry->getPropertyValue('dwm:className')->getIdOrValue();

            $newEntry['description'] = $rdfEntry->getPropertyValue('rdfs:comment')->getIdOrValue();

            $newEntry['properties'] = $this->graph->getPropertyInfoForTargetClassByNodeShape($rdfEntry->getId());

            return $newEntry;
        }, $subGraph->getEntries());
    }

    #[ProcessStep()]
    protected function createMapForDBRelatedClasses(): void
    {
        $resultsFolder = $this->dwmConfig->getResultFolderPath();
        if (is_string($resultsFolder) && file_exists($resultsFolder)) {
            /** @var array<string> */
            $content = [];
            $content[] = '# Map of Database Related Classes';
            $content[] = '';

            array_map(function ($classConfig) use (&$content) {
                /** @var array<string,string|array<string,string>> */
                $classConfig = $classConfig;

                // name
                /** @var string */
                $className = $classConfig['className'];
                $content[] = '## '.$className;
                $content[] = '';

                // description
                /** @var string */
                $description = $classConfig['description'];
                $content[] = $description;
                $content[] = '';

                // properties
                /** @var array<string,string> */
                $properties = $classConfig['properties'];
                if (0 == count($properties)) {
                    $content[] = '**Note:** This class does not have any property information.';
                } else {
                    $content[] = '**Properties:**';
                    foreach ($properties as $property) {
                        /** @var array<string,string> */
                        $property = $property;

                        $content[] = '* '.$property['propertyName'].' ('.$property['datatype'].')';
                    }
                }
            }, $this->classConfig);

            $content = implode(PHP_EOL, $content);

            file_put_contents($this->dwmConfig->getResultFolderPath().'/mapOfDBRelatedClasses.md', $content);
        } else {
            $msg = 'Results folder path not set or folder does not exist: '.$this->dwmConfig->getResultFolderPath();
            throw new Exception($msg);
        }
    }

    #[ProcessStep()]
    protected function createMapForProcesses(): void
    {
        // we checked result folder path before, therefore not neccessary again

        $subGraph = $this->graph->getSubGraphWithEntriesOfType('dwm:Process');

        /** @var array<string> */
        $content = [];
        $content[] = '# Map of Processes';

        array_map(function ($process) use (&$content) {
            /** @var \DWM\RDF\RDFEntry */
            $process = $process;

            // get process name
            /** @var string */
            $processName = $process->getPropertyValue('dwm:classPath')->getIdOrValue();
            $pos = (int) strrpos($processName, '\\');
            $pos = 0 < $pos ? $pos + 1 : 0;
            $processName = substr($processName, $pos);

            $content[] = '';
            $content[] = '';
            $content[] = '## '.$processName;
            $content[] = '';

            // required steps
            $content[] = '**Required steps:**';

            foreach ($process->getPropertyValues('dwm:requiredSteps') as $i => $rdfValue) {
                $content[] = ++$i.'. '.$rdfValue->getIdOrValue();
            }
        }, $subGraph->getEntries());

        $content = implode(PHP_EOL, $content);

        file_put_contents($this->dwmConfig->getResultFolderPath().'/mapOfProcesses.md', $content);
    }
}
