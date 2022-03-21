<?php

declare(strict_types=1);

namespace DWM\Process;

use DWM\Attribute\ProcessStep;
use DWM\DWMConfig;
use DWM\Result\ProcessKnowlegeCheckResult;
use DWM\SimpleStructure\Process;
use Exception;
use ML\JsonLD\JsonLD;
use ML\JsonLD\NQuads;
use ReflectionClass;

class VerifyProcessKnowledgeMatchesCode extends Process
{
    private string $currentPath;

    private DWMConfig $dwmConfig;

    /**
     * @var array<\stdClass>
     */
    private array $jsonLdArr;

    /**
     * @var array<mixed>
     */
    private array $processRelatedKnowledge = [];

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

        $this->addStep('readMergedKnowledge');

        $this->addStep('collectProcessRelatedKnowledge');

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
    protected function readMergedKnowledge(): void
    {
        $mergedFilePath = $this->dwmConfig->getMergedKnowledgeFilePath();

        if (true === is_string($mergedFilePath) && file_exists($mergedFilePath)) {
            $content = file_get_contents($mergedFilePath);
            if (is_string($content)) {
                $nquads = new NQuads();
                $quads = $nquads->parse($content);
                $this->jsonLdArr = JsonLD::fromRdf($quads);
            } else {
                throw new Exception('Could not read content of '.$mergedFilePath);
            }
        } else {
            throw new Exception('Merged knowledge file doesn not exist: '.$mergedFilePath);
        }
    }

    #[ProcessStep()]
    protected function collectProcessRelatedKnowledge(): void
    {
        $processRelatedKnowledge = array_map(function ($jsonLdStdClassInstance) {
            $propertyValuePairs = get_object_vars($jsonLdStdClassInstance);

            /** @var array<string> */
            $typeList = $propertyValuePairs['@type'] ?? [];
            if (
                isset($propertyValuePairs['@type'])
                && in_array($this->dwmConfig->getPrefix().'Process', $typeList, true)
            ) {
                /** @var array<mixed> */
                $requiredSteps = $propertyValuePairs[$this->dwmConfig->getPrefix().'requiredSteps'];
                // get required steps (= method names)
                $requiredSteps = array_map(function ($item) {
                    return get_object_vars((object) $item)['@value'];
                }, $requiredSteps);

                /**
                 * get class path
                 */
                /** @var array<mixed> */
                $arr = $propertyValuePairs[$this->dwmConfig->getPrefix().'classPath'];
                /** @var object */
                $obj = $arr[0];
                $classPath = get_object_vars($obj)['@value'];

                // return extracted information
                return [
                    'id' => $propertyValuePairs['@id'],
                    'classPath' => $classPath,
                    'requiredSteps' => $requiredSteps,
                ];
            }
        }, $this->jsonLdArr);

        $processRelatedKnowledge = array_filter($processRelatedKnowledge);

        $this->processRelatedKnowledge = $processRelatedKnowledge;
    }

    #[ProcessStep()]
    protected function checkClassExistence(): void
    {
        $processKnowlegeCheckResult = $this->processKnowlegeCheckResult;

        array_map(function ($processInfo) use ($processKnowlegeCheckResult) {
            /** @var array<string,string> */
            $processInfo = $processInfo;
            if (class_exists($processInfo['classPath'])) {
                // OK
                $processKnowlegeCheckResult->addExistingClass($processInfo['classPath']);
            } else {
                // Fail
                $processKnowlegeCheckResult->addNonExistingClass($processInfo['classPath']);
            }
        }, $this->processRelatedKnowledge);
    }

    #[ProcessStep()]
    protected function checkProcessClassHasRequiredSteps(): void
    {
        $processKnowlegeCheckResult = $this->processKnowlegeCheckResult;

        array_map(function ($processInfo) use ($processKnowlegeCheckResult) {
            /** @var array<mixed> */
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
                $processKnowlegeCheckResult->addClassWithMissingRequiredSteps($classPath, $missingMethods);
            }
        }, $this->processRelatedKnowledge);
    }

    #[ProcessStep()]
    protected function checkThatProcessStepAmountIsEqual(): void
    {
        $processKnowlegeCheckResult = $this->processKnowlegeCheckResult;

        array_map(function ($processInfo) use ($processKnowlegeCheckResult) {
            /** @var array<mixed> */
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
                $processKnowlegeCheckResult->addClassWithMoreStepsThanRequired($classPath, $diff);
            }
        }, $this->processRelatedKnowledge);
    }

    #[ProcessStep()]
    protected function generateReport(): void
    {
        $this->processKnowlegeCheckResult->writeReport($this->dwmConfig->getResultFolderPath().'/processKnowlege.md');
    }
}
