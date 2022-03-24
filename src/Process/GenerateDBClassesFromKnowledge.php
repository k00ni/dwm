<?php

declare(strict_types=1);

namespace DWM\Process;

use DWM\Attribute\ProcessStep;
use DWM\DWMConfig;
use DWM\RDF\RDFGraph;
use DWM\SimpleStructure\Process;
use Exception;

class GenerateDBClassesFromKnowledge extends Process
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

        $this->addStep('getDBRelatedClassesAndMetaData');

        $this->addStep('generatePHPCode');

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
    protected function getDBRelatedClassesAndMetaData(): void
    {
        $subGraph = $this->graph->getSubGraphWithEntriesWithPropertyValue('dwm:isStoredInDatabase', 'true');

        $this->classConfig = array_map(function ($rdfEntry) {
            /** @var array<string,string|array<mixed>> */
            $newEntry = ['className' => null, 'properties' => []];

            /** @var \DWM\RDF\RDFEntry */
            $rdfEntry = $rdfEntry;

            $newEntry['classUri'] = $rdfEntry->getId();

            // get NodeShape ID
            $subGraph = $this->graph->getSubGraphWithEntriesWithPropertyValue('sh:targetClass', $rdfEntry->getId());
            if (1 == $subGraph->count()) {
                $newEntry['relatedNodeShapeId'] = $subGraph->getEntries()[0]->getId();
            }

            // class name
            $newEntry['className'] = $rdfEntry->getPropertyValue('dwm:className')->getIdOrValue();

            $newEntry['properties'] = $this->graph->getPropertyInfoForTargetClassByNodeShape($rdfEntry->getId());

            return $newEntry;
        }, $subGraph->getEntries());
    }

    /**
     * @param array<mixed> $classConfig
     */
    private function createFileContent(array $classConfig): string
    {
        $content = [];

        $content[] = '<?php';
        $content[] = '';

        // namespace
        $content[] = 'namespace '.$this->dwmConfig->getGeneratedDBClassFilesPHPNamespace().';';
        $content[] = '';

        // notice
        $content[] = '/**';
        $content[] = ' * Auto generated. Changes will be overriden.';
        $content[] = ' *';
        $content[] = ' * @dwm-class-id '.$classConfig['classUri'];
        $content[] = ' * @dwm-nodeshape-id '.$classConfig['relatedNodeShapeId'];
        $content[] = ' */';

        // class
        $content[] = 'class '.$classConfig['className'];
        $content[] = '{';

        /** @var array<array<string,string>> */
        $properties = $classConfig['properties'];
        // for each property also add getter and setter
        foreach ($properties as $property) {
            $pos = strpos($property['datatype'], '#');
            $pos = false === $pos ? 0 : $pos + 1;
            $datatype = substr($property['datatype'], $pos);

            // property itself
            $content[] = '    private '.$datatype.' $'.$property['propertyName'].';';

            // getter
            $content[] = '';
            $functionName = 'get'.ucfirst($property['propertyName']);
            $content[] = '    public function '.$functionName.'(): '.$datatype;
            $content[] = '    {';
            $content[] = '        return $this->'.$property['propertyName'].';';
            $content[] = '    }';

            // setter
            $content[] = '';
            $functionName = 'set'.ucfirst($property['propertyName']);
            $content[] = '    public function '.$functionName.'('.$datatype.' $value): void';
            $content[] = '    {';
            $content[] = '        $this->'.$property['propertyName'].' = $value;';
            $content[] = '    }';
        }

        $content[] = '}';

        return implode(PHP_EOL, $content);
    }

    #[ProcessStep()]
    protected function generatePHPCode(): void
    {
        array_map(function ($classConfig) {
            /** @var array<mixed> */
            $classConfig = $classConfig;

            // build path to class file
            $classFilePath = $this->dwmConfig->getGeneratedDBClassFilesPath();
            $classFilePath .= '/'.$classConfig['className'].'.php';

            // remove old class file, if available
            if (file_exists($classFilePath)) {
                if (unlink($classFilePath)) {
                    // OK
                } else {
                    throw new Exception('Could not remove old PHP class file: '.$classFilePath);
                }
            }

            // create new file
            $fileContent = $this->createFileContent($classConfig);
            file_put_contents($classFilePath, $fileContent);
        }, $this->classConfig);
    }
}
