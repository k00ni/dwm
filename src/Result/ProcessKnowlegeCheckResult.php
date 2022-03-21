<?php

declare(strict_types=1);

namespace DWM\Result;

final class ProcessKnowlegeCheckResult
{
    /**
     * @var array<mixed>
     */
    private array $classesWithMissingRequiredSteps = [];

    /**
     * @var array<mixed>
     */
    private array $classesWithMoreStepsThanRequired = [];

    /**
     * @var array<string>
     */
    private array $existingClasses = [];

    /**
     * @var array<string>
     */
    private array $nonExistingClasses = [];

    /**
     * @param array<string> $missingRequiredSteps
     */
    public function addClassWithMissingRequiredSteps(string $classPath, array $missingRequiredSteps): void
    {
        $this->classesWithMissingRequiredSteps[] = [
            'classPath' => $classPath,
            'missingRequiredSteps' => $missingRequiredSteps,
        ];
    }

    /**
     * @param array<string> $additionalSteps
     */
    public function addClassWithMoreStepsThanRequired(string $classPath, array $additionalSteps): void
    {
        $this->classesWithMoreStepsThanRequired[] = [
            'classPath' => $classPath,
            'additionalSteps' => $additionalSteps,
        ];
    }

    public function addExistingClass(string $classPath): void
    {
        $this->existingClasses[] = $classPath;
    }

    public function addNonExistingClass(string $classPath): void
    {
        $this->nonExistingClasses[] = $classPath;
    }

    /**
     * @return array<mixed>
     */
    public function getClassesWithMissingRequiredSteps(): array
    {
        return $this->classesWithMissingRequiredSteps;
    }

    /**
     * @return array<mixed>
     */
    public function getClassesWithMoreStepsThanRequired(): array
    {
        return $this->classesWithMoreStepsThanRequired;
    }

    /**
     * @return array<string>
     */
    public function getExistingClasses(): array
    {
        return $this->existingClasses;
    }

    /**
     * @return array<string>
     */
    public function getNonExistingClasses(): array
    {
        return $this->nonExistingClasses;
    }

    /**
     * @todo move that into a dedicated writer class (With different output formats)
     */
    public function writeReport(string $reportFilePath): void
    {
        /** @var array<string> */
        $output = [];
        $output[] = '# Process Knowledge Result';
        $output[] = '';

        // existing classes
        $output[] = 'The following classes were found:';

        if (0 < count($this->getExistingClasses())) {
            array_map(function ($classPath) use (&$output) {
                $output[] = '* '.$classPath;
            }, $this->getExistingClasses());
        } else {
            $output[] = '*(Nothing found)*';
        }

        $output[] = '';
        $output[] = '';

        // non existing classes
        $output[] = 'The following classes do not exist:';

        if (0 < count($this->getNonExistingClasses())) {
            array_map(function ($classPath) use (&$output) {
                $output[] = '* '.$classPath;
            }, $this->getNonExistingClasses());
        } else {
            $output[] = '*(Nothing found)*';
        }

        $output[] = '';
        $output[] = '';

        // classes with missing required steps
        $output[] = 'The following classes have missing required steps:';

        if (0 < count($this->getClassesWithMissingRequiredSteps())) {
            array_map(function ($entry) use (&$output) {
                /** @var array<mixed> */
                $entry = $entry;
                /** @var string */
                $classPath = $entry['classPath'];
                /** @var array<string> */
                $missingRequiredSteps = $entry['missingRequiredSteps'];
                $output[] = '* '.$classPath.' ('.implode(', ', $missingRequiredSteps).')';
            }, $this->getClassesWithMissingRequiredSteps());
        } else {
            $output[] = '*(Nothing found)*';
        }

        $output[] = '';
        $output[] = '';

        // classes with missing required steps
        $output[] = 'The following classes have more steps than required:';

        if (0 < count($this->getClassesWithMoreStepsThanRequired())) {
            array_map(function ($entry) use (&$output) {
                /** @var array<mixed> */
                $entry = $entry;
                /** @var string */
                $classPath = $entry['classPath'];
                /** @var array<string> */
                $additionalSteps = $entry['additionalSteps'];
                $output[] = '* '.$classPath.' ('.implode(', ', $additionalSteps).')';
            }, $this->getClassesWithMoreStepsThanRequired());
        } else {
            $output[] = '*(Nothing found)*';
        }

        // write result file
        file_put_contents($reportFilePath, implode(PHP_EOL, $output));
    }
}
