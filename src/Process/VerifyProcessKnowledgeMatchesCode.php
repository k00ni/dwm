<?php

declare(strict_types=1);

namespace DWM\Process;

use DWM\Attribute\ProcessStep;
use DWM\SimpleStructure\Process;
use Exception;
use ML\JsonLD\JsonLD;
use ML\JsonLD\NQuads;
use ReflectionClass;

class VerifyProcessKnowledgeMatchesCode extends Process
{
    private string $currentPath;

    /**
     * @var array<mixed>
     */
    private array $dwmConfigArray;

    /**
     * @todo move to JSONLD
     */
    private string $dwmConfigFilename = 'dwm.json';

    private string $dwmPrefix = 'https://github.com/k00ni/dwm#';

    /**
     * @var array<\stdClass>
     */
    private array $jsonLdArr;

    /**
     * @todo move to JSONLD
     */
    private string $mergedKnowledgeFilepath = '__merged_knowledge.nt';

    /**
     * @var array<mixed>
     */
    private array $processRelatedKnowledge = [];

    public function __construct()
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
    }

    #[ProcessStep()]
    protected function loadDwmJson(): void
    {
        $this->dwmConfigArray = loadDwmJsonAndGetArrayRepresentation($this->currentPath.'/'.$this->dwmConfigFilename);
    }

    #[ProcessStep()]
    protected function readMergedKnowledge(): void
    {
        /** @var array<mixed> */
        $location = $this->dwmConfigArray['knowledge-location'];

        $mergedKnowledgeFilepath = $this->currentPath;
        $mergedKnowledgeFilepath .= '/'.$location['root-folder'];
        $mergedKnowledgeFilepath .= '/'.$this->mergedKnowledgeFilepath;

        if (file_exists($mergedKnowledgeFilepath)) {
            $content = file_get_contents($mergedKnowledgeFilepath);
            if (is_string($content)) {
                $nquads = new NQuads();
                $quads = $nquads->parse($content);
                $this->jsonLdArr = JsonLD::fromRdf($quads);
            } else {
                throw new Exception('Could not read content of '.$mergedKnowledgeFilepath);
            }
        } else {
            throw new Exception('Merged knowledge file doesn not exist: '.$mergedKnowledgeFilepath);
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
                && in_array($this->dwmPrefix.'Process', $typeList)
            ) {
                /** @var array<mixed> */
                $requiredSteps = $propertyValuePairs[$this->dwmPrefix.'required_steps'];
                // get required steps (= method names)
                $requiredSteps = array_map(function ($item) {
                    return get_object_vars((object) $item)['@value'];
                }, $requiredSteps);

                /**
                 * get class path
                 */
                /** @var array<mixed> */
                $arr = $propertyValuePairs[$this->dwmPrefix.'class_path'];
                /** @var object */
                $obj = $arr[0];
                $classPath = get_object_vars($obj)['@value'];

                // return extracted information
                return [
                    'id' => $propertyValuePairs['@id'],
                    'class_path' => $classPath,
                    'required_steps' => $requiredSteps,
                ];
            }
        }, $this->jsonLdArr);

        $processRelatedKnowledge = array_filter($processRelatedKnowledge);

        $this->processRelatedKnowledge = $processRelatedKnowledge;
    }

    #[ProcessStep()]
    protected function checkClassExistence(): void
    {
        $unavailableClasses = array_map(function ($processInfo) {
            /** @var array<string,string> */
            $processInfo = $processInfo;
            if (class_exists($processInfo['class_path'])) {
                // OK
            } else {
                return $processInfo['class_path'];
            }
        }, $this->processRelatedKnowledge);

        $unavailableClasses = array_filter($unavailableClasses);

        if (0 < count($unavailableClasses)) {
            $msg = 'The following classes do not exist: ';
            $msg .= implode(', ', $unavailableClasses);

            throw new Exception($msg);
        }
    }

    #[ProcessStep()]
    protected function checkProcessClassHasRequiredSteps(): void
    {
        $classesWithMissingMethods = array_map(function ($processInfo) {
            /** @var array<mixed> */
            $processInfo = $processInfo;
            /** @var class-string<\DWM\SimpleStructure\Process> */
            $classPath = $processInfo['class_path'];
            /** @var array<string> */
            $requiredSteps = $processInfo['required_steps'];

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
                return [
                    'class_path' => $classPath,
                    'missing_methods' => $missingMethods,
                ];
            }
        }, $this->processRelatedKnowledge);

        $classesWithMissingMethods = array_filter($classesWithMissingMethods);

        if (0 < count($classesWithMissingMethods)) {
            $msg = 'The following classes have missing methods: ';

            $parts = array_map(function ($item) {
                return 'Class "'.$item['class_path'].'" ('
                    .implode(', ', $item['missing_methods'])
                    .')';
            }, $classesWithMissingMethods);

            $msg .= implode(' // ', $parts);

            throw new Exception($msg);
        }
    }

    #[ProcessStep()]
    protected function checkThatProcessStepAmountIsEqual(): void
    {
        $classesWithMissingRequiredSteps = array_map(function ($processInfo) {
            /** @var array<mixed> */
            $processInfo = $processInfo;
            /**
             * @var class-string<\DWM\SimpleStructure\Process>
             */
            $classPath = $processInfo['class_path'];

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
            $requiredSteps = $processInfo['required_steps'];

            // check found step methods amount and required_steps from knowledge base
            $diff = array_diff($stepMethods, $requiredSteps);
            if (0 < count($diff)) {
                return [
                    'class_path' => $classPath,
                    'missing_required_steps' => $diff,
                ];
            }
        }, $this->processRelatedKnowledge);

        $classesWithMissingRequiredSteps = array_filter($classesWithMissingRequiredSteps);

        if (0 < count($classesWithMissingRequiredSteps)) {
            $msg = 'The following classes are missing knowledge about step methods: ';

            $parts = array_map(function ($item) {
                /** @var array<mixed> */
                $item = $item;
                /** @var string */
                $classPath = $item['class_path'];
                /** @var array<string> */
                $missingSteps = $item['missing_required_steps'];

                return 'Class "'.$classPath.'" ('.implode(', ', $missingSteps).')';
            }, $classesWithMissingRequiredSteps);

            $msg .= implode(' // ', $parts);

            throw new Exception($msg);
        }
    }
}
