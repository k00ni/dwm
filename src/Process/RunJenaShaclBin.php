<?php

declare(strict_types=1);

namespace DWM\Process;

use DWM\Attribute\ProcessStep;
use DWM\DWMConfig;
use DWM\SimpleStructure\Process;
use Exception;
use ML\JsonLD\JsonLD;
use ML\JsonLD\NQuads;

class RunJenaShaclBin extends Process
{
    private string $currentPath;

    private DWMConfig $dwmConfig;

    /**
     * @var array<string>
     */
    private array $output = [];

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

        $this->addStep('runCommand');

        $this->addStep('interpretResult');

        $this->dwmConfig = $dwmConfig;
    }

    #[ProcessStep()]
    protected function loadDwmJson(): void
    {
        $this->dwmConfig->load($this->currentPath);
    }

    #[ProcessStep()]
    protected function runCommand(): void
    {
        $command = $this->dwmConfig->getPathToJenaShaclBinFile();
        $command .= ' validate --data ';

        $command .= $this->dwmConfig->getMergedKnowledgeFilePath();

        $command = escapeshellcmd($command);

        exec($command, $this->output, $this->result);
    }

    #[ProcessStep()]
    protected function interpretResult(): void
    {
        // merge string list
        $output = implode(PHP_EOL, $this->output).PHP_EOL;

        // replace multiple whitepsaces with one
        $output = (string) preg_replace('/\s+/', ' ', $output);

        if (str_contains($output, 'sh:conforms true') && 0 == $this->result) {
            echo 'Run Jena SHACL bin: OK!';
            echo PHP_EOL;
        } else {
            echo $output;
        }
    }
}
