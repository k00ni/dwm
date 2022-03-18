<?php

declare(strict_types=1);

namespace DWM\Process;

use DWM\Attribute\ProcessStep;
use DWM\SimpleStructure\Process;
use FilesystemIterator;
use ML\JsonLD\JsonLD;
use ML\JsonLD\NQuads;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class MergeJsonLDFiles extends Process
{
    private string $currentPath;

    private array $dwmConfigArray;

    /**
     * @todo move to JSONLD
     */
    private string $dwmConfigFilename = 'dwm.json';

    private string $knowledgeFolderPath;
    private iterable $knowledgeFilepathIterator;

    /**
     * @todo move to JSONLD
     */
    private string $mergedKnowledgeFilenameNt = '__merged_knowledge.nt';

    public function __construct()
    {
        parent::__construct();

        $this->currentPath = getcwd();

        $this->addStep('loadDwmJson');

        $this->addStep('collectKnowledgeFilepaths');

        $this->addStep('mergeIntoNTriples');

        $this->verifyThisInstance();
    }

    #[ProcessStep()]
    protected function loadDwmJson(): void
    {
        $this->dwmConfigArray = loadDwmJsonAndGetArrayRepresentation($this->currentPath, $this->dwmConfigFilename);

        $this->knowledgeFolderPath = $this->currentPath.'/'.$this->dwmConfigArray['knowledge-location']['root-folder'];
    }

    #[ProcessStep()]
    protected function collectKnowledgeFilepaths(): void
    {
        $this->knowledgeFilepathIterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->knowledgeFolderPath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
    }

    #[ProcessStep()]
    protected function mergeIntoNTriples(): void
    {
        $nquads = new NQuads();
        $result = '';

        foreach ($this->knowledgeFilepathIterator as $path) {
            if ($path->isDir()) {
            } elseif ($path->isLink()) {
            } elseif (str_contains($path->getPathname(), '.jsonld')) {
                $quads = JsonLD::toRdf($path->getPathname());
                $result .= $nquads->serialize($quads);
            }
        }

        file_put_contents($this->knowledgeFolderPath.'/'.$this->mergedKnowledgeFilenameNt, $result);
    }
}
