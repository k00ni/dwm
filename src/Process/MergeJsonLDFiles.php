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

        if (is_string($this->dwmConfig->getMergedKnowledgeFilePath())) {
            file_put_contents($this->dwmConfig->getMergedKnowledgeFilePath(), $result);
        } else {
            throw new Exception('File path of merged knowledge file is null.');
        }
    }
}
