<?php

declare(strict_types=1);

namespace DWM\Process;

use DWM\Attribute\ProcessStep;
use DWM\DWMConfig;
use DWM\RDF\RDFGraph;
use DWM\Result\ProcessKnowlegeCheckResult;
use DWM\SimpleStructure\Process;
use Exception;
use ReflectionClass;

class VerifyProcessKnowledgeMatchesCode extends Process
{
    private string $currentPath;

    private DWMConfig $dwmConfig;

    private RDFGraph $graph;

    /**
     * @var array<mixed>
     */
    private array $processInfo;

    private ProcessKnowlegeCheckResult $processKnowlegeCheckResult;

    public function __construct(DWMConfig $dwmConfig, ProcessKnowlegeCheckResult $processKnowlegeCheckResult)
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

        $this->addStep('collectProcessClassPathAndRequiredSteps');

        $this->addStep('checkClassExistence');

        $this->addStep('checkProcessClassHasRequiredSteps');

        $this->addStep('checkThatProcessStepAmountIsEqual');

        $this->addStep('generateReport');

        $this->dwmConfig = $dwmConfig;
        $this->processKnowlegeCheckResult = $processKnowlegeCheckResult;
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

        if (true === is_string($mergedFilePath)) {
            $this->graph = new RDFGraph();
            $this->graph->initializeWithMergedKnowledgeJsonLDFile($mergedFilePath);
        } else {
            throw new Exception('Merged knowledge file doesn not exist: '.$mergedFilePath);
        }
    }

    #[ProcessStep()]
    protected function collectProcessClassPathAndRequiredSteps(): void
    {
        // get subgraph which contains only instances of dwm:Process
        $subGraph = $this->graph->getSubGraphWithEntriesOfType('dwm:Process');

        $this->processInfo = array_map(function ($rdfEntry) {
            /** @var \DWM\RDF\RDFEntry */
            $rdfEntry = $rdfEntry;
            $newEntry = ['classPath' => null, 'requiredSteps' => []];

            // class path
            if ($rdfEntry->hasProperty('dwm:classPath')) {
                $newEntry['classPath'] = $rdfEntry->getPropertyValue('dwm:classPath')->getIdOrValue();
            } else {
                throw new Exception('No dwm:classPath property found.');
            }

            // required steps
            if ($rdfEntry->hasProperty('dwm:requiredSteps')) {
                $values = $rdfEntry->getPropertyValues('dwm:requiredSteps');
                foreach ($values as $value) {
                    $newEntry['requiredSteps'][] = $value->getIdOrValue();
                }
            } else {
                throw new Exception('No dwm:requiredSteps property found.');
            }

            return $newEntry;
        }, $subGraph->getEntries());
    }

    #[ProcessStep()]
    protected function checkClassExistence(): void
    {
        array_map(function ($processInfo) {
            /** @var array<mixed> */
            $processInfo = $processInfo;
            /** @var string */
            $classPath = $processInfo['classPath'];
            if (class_exists($classPath)) {
                // OK
                $this->processKnowlegeCheckResult->addExistingClass($classPath);
            } else {
                // Fail
                $this->processKnowlegeCheckResult->addNonExistingClass($classPath);
            }
        }, $this->processInfo);
    }

    #[ProcessStep()]
    protected function checkProcessClassHasRequiredSteps(): void
    {
        array_map(function ($processInfo) {
            /** @var array<string,string|array<string>> */
            $processInfo = $processInfo;
            /** @var class-string<\DWM\SimpleStructure\Process> */
            $classPath = $processInfo['classPath'];
            /** @var array<string> */
            $requiredSteps = $processInfo['requiredSteps'];

            $reflectionClass = new ReflectionClass($classPath);

            $missingMethods = array_map(function ($methodName) use ($reflectionClass) {
                if ($reflectionClass->hasMethod($methodName)) {
                    // OK
                } else {
                    return $methodName;
                }
            }, $requiredSteps);

            $missingMethods = array_filter($missingMethods);

            if (0 < count($missingMethods)) {
                $this->processKnowlegeCheckResult->addClassWithMissingRequiredSteps($classPath, $missingMethods);
            }
        }, $this->processInfo);
    }

    #[ProcessStep()]
    protected function checkThatProcessStepAmountIsEqual(): void
    {
        array_map(function ($processInfo) {
            /** @var array<string,string|array<string>> */
            $processInfo = $processInfo;
            /**
             * @var class-string<\DWM\SimpleStructure\Process>
             */
            $classPath = $processInfo['classPath'];

            $reflectionClass = new ReflectionClass($classPath);

            $stepMethods = array_map(function ($reflectionMethod) {
                $attributes = array_map(function ($reflectionAttribute) {
                    if ('DWM\\Attribute\\ProcessStep' == $reflectionAttribute->getName()) {
                        return 1;
                    }
                }, $reflectionMethod->getAttributes());
                $attributes = array_filter($attributes);

                if (0 < count($attributes)) {
                    return $reflectionMethod->getName();
                }
            }, $reflectionClass->getMethods());

            $stepMethods = array_filter($stepMethods);
            /** @var array<string> */
            $requiredSteps = $processInfo['requiredSteps'];

            // check found step methods amount and requiredSteps from knowledge base
            $diff = array_diff($stepMethods, $requiredSteps);
            if (0 < count($diff)) {
                $this->processKnowlegeCheckResult->addClassWithMoreStepsThanRequired($classPath, $diff);
            }
        }, $this->processInfo);
    }

    #[ProcessStep()]
    protected function generateReport(): void
    {
        $this->result = $this->processKnowlegeCheckResult->getFoundError() ? 1 : 0;

        echo $this->processKnowlegeCheckResult->generateReport();
    }
}
