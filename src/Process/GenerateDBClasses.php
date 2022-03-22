<?php

declare(strict_types=1);

namespace DWM\Process;

use DWM\Attribute\ProcessStep;
use DWM\DWMConfig;
use DWM\SimpleStructure\Process;
use Exception;
use ML\JsonLD\JsonLD;
use ML\JsonLD\NQuads;
use Rs\Json\Pointer;

class GenerateDBClasses extends Process
{
    private string $currentPath;

    private DWMConfig $dwmConfig;

    private string $shPropertyUri = 'http://www.w3.org/ns/shacl#property';
    private string $shTargetClassUri = 'http://www.w3.org/ns/shacl#targetClass';

    /**
     * @var array<mixed>
     */
    private array $relevantClasses;

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

        $this->addStep('readMergedKnowledge');

        $this->dwmConfig = $dwmConfig;
    }

    #[ProcessStep()]
    protected function loadDwmJson(): void
    {
        $this->dwmConfig->load($this->currentPath);
    }

    #[ProcessStep()]
    protected function readMergedKnowledge(): void
    {
        $mergedJsonLD = file_get_contents($this->dwmConfig->getMergedKnowledgeJsonLDFilePath());

        /** @var array<mixed> */
        $arrRepresentation = json_decode($mergedJsonLD, true);

        // TODO RDFGraph




        // get all classes


        // get properties per class
    }
}
