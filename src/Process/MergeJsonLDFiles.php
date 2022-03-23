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

    /**
     * Replaces all blank nodes by a random identifier so we can merge different JSON LD documents.
     */
    private function deployRandomIdentifiersForBlankNodes(string $nquadsString): string
    {
        $regex = '/(_:b[0-9]{1,})/mis';
        preg_match_all($regex, $nquadsString, $matches);

        if (isset($matches[1])) {
            $matches = array_unique($matches[1]);

            // sort matches from longest to shortest
            usort($matches, function ($a, $b) {
                return strlen($b) - strlen($a);
            });

            // assign random ID to each match (_:b0 => _:bad93fa1)
            $matchesWithNewIdentifiers = [];
            foreach ($matches as $match) {
                $bytes = random_bytes(5);
                $matchesWithNewIdentifiers[$match] = '_:b'.bin2hex($bytes);
            }

            // replace old blank nodes with new IDs
            foreach ($matchesWithNewIdentifiers as $oldBlankId => $newBlankId) {
                /** @var string */
                $oldBlankId = $oldBlankId;
                $nquadsString = str_replace($oldBlankId, $newBlankId, $nquadsString);
            }
        }

        return $nquadsString;
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
            $nquadsString = $nquads->serialize($quads);

            // deploy random identifiers for blank nodes (e.g. _:b0 => _:bf9fd9fa)
            $nquadsString = $this->deployRandomIdentifiersForBlankNodes($nquadsString);

            $result .= $nquadsString;
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

        $path = $this->dwmConfig->getMergedKnowledgeNtFilePath();
        if (is_string($path)) {
            $ntriples = file_get_contents($path);

            if (false === $ntriples) {
                throw new Exception('Loading content of merged knowledge NT file failed.');
            }

            $quads = $nquads->parse($ntriples);
            $document = JsonLD::fromRdf($quads);

            $jsonLD = JsonLD::toString($document, true);

            $path = $this->dwmConfig->getMergedKnowledgeJsonLDFilePath();
            if (is_string($path)) {
                file_put_contents($path, $jsonLD);
            } else {
                throw new Exception('Dwm configs mergedKnowledgeJsonLDFilePath is not set.');
            }
        } else {
            throw new Exception('Dwm configs mergedKnowledgeNtFilePath is not set.');
        }
    }
}
