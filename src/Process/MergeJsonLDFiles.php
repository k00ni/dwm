<?php

declare(strict_types=1);

namespace DWM\Process;

use DWM\Attribute\ProcessStep;
use DWM\SimpleStructure\Process;
use Exception;
use FilesystemIterator;
use ML\JsonLD\JsonLD;
use ML\JsonLD\NQuads;
use OuterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class MergeJsonLDFiles extends Process
{
    private string $currentPath;

    /**
     * @var array<string,string>
     */
    private array $dwmConfigArray;

    /**
     * @todo move to JSONLD
     */
    private string $dwmConfigFilename = 'dwm.json';

    private string $knowledgeFolderPath;

    /**
     * @var \SplFileInfo[]
     */
    private iterable $knowledgeFilepathIterator;

    /**
     * @todo move to JSONLD
     */
    private string $mergedKnowledgeFilenameNt = '__merged_knowledge.nt';

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

        $this->addStep('collectKnowledgeFilepaths');

        $this->addStep('mergeIntoNTriples');
    }

    #[ProcessStep()]
    protected function loadDwmJson(): void
    {
        $this->dwmConfigArray = loadDwmJsonAndGetArrayRepresentation($this->currentPath.'/'.$this->dwmConfigFilename);

        /** @var array<mixed> */
        $location = $this->dwmConfigArray['knowledge-location'];
        /** @var string */
        $rootFolder = $location['root-folder'];
        $this->knowledgeFolderPath = $this->currentPath.'/'.$rootFolder;
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
            /** @var \SplFileInfo */
            $path = $path;
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
