<?php

declare(strict_types=1);

namespace DWM\Process;

use DWM\Attribute\ProcessStep;
use DWM\DWMConfig;
use DWM\SimpleStructure\Process;
use Exception;
use ML\JsonLD\JsonLD;
use ML\JsonLD\NQuads;

class MergeJsonLDFiles extends Process
{
    private string $currentPath;

    private DWMConfig $dwmConfig;

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

        $this->addStep('mergeIntoNTriples');

        $this->addStep('mergeIntoJsonLD');

        $this->dwmConfig = $dwmConfig;
    }

    #[ProcessStep()]
    protected function loadDwmJson(): void
    {
        $this->dwmConfig->load($this->currentPath);
    }

    #[ProcessStep()]
    protected function mergeIntoNTriples(): void
    {
        $nquads = new NQuads();
        $result = '';

        array_map(function ($filePath) use ($nquads, &$result) {
            /** @var string */
            $filePath = $filePath;

            $quads = JsonLD::toRdf($filePath);
            $result .= $nquads->serialize($quads);
        }, $this->dwmConfig->getKnowledgeFilePaths());

        if (is_string($this->dwmConfig->getMergedKnowledgeNtFilePath())) {
            file_put_contents($this->dwmConfig->getMergedKnowledgeNtFilePath(), $result);
        } else {
            throw new Exception('File path of merged knowledge file is null.');
        }
    }

    #[ProcessStep()]
    protected function mergeIntoJsonLD(): void
    {
        $nquads = new NQuads();
        $ntriples = file_get_contents($this->dwmConfig->getMergedKnowledgeNtFilePath());

        $quads = $nquads->parse($ntriples);
        $document = JsonLD::fromRdf($quads);

        $jsonLD = JsonLD::toString($document, true);

        file_put_contents($this->dwmConfig->getMergedKnowledgeJsonLDFilePath(), $jsonLD);
    }
}
