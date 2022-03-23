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

    private bool $foundError = false;

    /**
     * @var array<string>
     */
    private array $nonExistingClasses = [];

    /**
     * @param array<string> $missingRequiredSteps
     */
    public function addClassWithMissingRequiredSteps(string $classPath, array $missingRequiredSteps): void
    {
        $this->foundError = true;

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
        $this->foundError = true;

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
        $this->foundError = true;

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

    public function getFoundError(): bool
    {
        return $this->foundError;
    }

    /**
     * @return array<string>
     */
    public function getNonExistingClasses(): array
    {
        return $this->nonExistingClasses;
    }

    private function colorTextGreen(string $line): string
    {
        return "\033[42m".$line."\033[0m";
    }

    private function colorTextRed(string $line): string
    {
        return "\033[41m".$line."\033[0m";
    }

    /**
     * @todo move that into a dedicated writer class (With different output formats)
     */
    public function generateReport(): string
    {
        /** @var array<string> */
        $output = [];
        $output[] = '';
        $output[] = 'DWM - Report for process knowledge check';
        $output[] = '';

        // non existing classes
        if (0 < count($this->getNonExistingClasses())) {
            $output[] = '';
            $output[] = $this->colorTextRed('The following classes do not exist:');
            array_map(function ($classPath) use (&$output) {
                $output[] = '* '.$classPath;
            }, $this->getNonExistingClasses());

            $output[] = '';
        }

        // classes with missing required steps
        if (0 < count($this->getClassesWithMissingRequiredSteps())) {
            $output[] = '';
            $output[] = $this->colorTextRed('The following classes have missing required steps:');
            array_map(function ($entry) use (&$output) {
                /** @var array<mixed> */
                $entry = $entry;
                /** @var string */
                $classPath = $entry['classPath'];
                /** @var array<string> */
                $missingRequiredSteps = $entry['missingRequiredSteps'];
                $output[] = '* '.$classPath.' ('.implode(', ', $missingRequiredSteps).')';
            }, $this->getClassesWithMissingRequiredSteps());

            $output[] = '';
        }

        // classes with missing required steps
        if (0 < count($this->getClassesWithMoreStepsThanRequired())) {
            $output[] = '';
            $output[] = $this->colorTextRed('The following classes have more steps than required:');
            array_map(function ($entry) use (&$output) {
                /** @var array<mixed> */
                $entry = $entry;
                /** @var string */
                $classPath = $entry['classPath'];
                /** @var array<string> */
                $additionalSteps = $entry['additionalSteps'];
                $output[] = '* '.$classPath.' ('.implode(', ', $additionalSteps).')';
            }, $this->getClassesWithMoreStepsThanRequired());
        }

        if ($this->foundError) {
            $output[] = '';
            $output[] = $this->colorTextRed('FAIL - Errors found');
        } else {
            $output[] = $this->colorTextGreen('OK - No Errors found');
        }

        $output[] = '';
        $output[] = '';

        return implode(PHP_EOL, $output);
    }
}
