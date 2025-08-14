<?php
namespace App\Neuron\Overrides;

use NeuronAI\RAG\Document;
use NeuronAI\Exceptions\VectorStoreException;
use NeuronAI\RAG\VectorStore\VectorSimilarity;
use NeuronAI\RAG\VectorStore\VectorStoreInterface;


class FileVectorStore extends \NeuronAI\RAG\VectorStore\FileVectorStore
{
    public function __construct(
        protected string $directory,
        protected int $topK = 4,
        protected string $name = 'neuron',
        protected string $ext = '.store'
    )
    {
        parent::__construct($directory, $topK, $name, $ext);
    }
    
    public function addDocuments(array $documents): VectorStoreInterface
    {
        $this->appendToFile(
            \array_map(fn (Document $document): array => $document->jsonSerialize(), $documents)
        );
        return $this;
    }
    
    public function deleteBySource(string $sourceType, string $sourceName): VectorStoreInterface
    {
        // Temporary file
        $tmpFile = $this->directory . \DIRECTORY_SEPARATOR . $this->name.'_tmp'.$this->ext;

        // Create a temporary file handle
        $tempHandle = \fopen($tmpFile, 'w');
        if (!$tempHandle) {
            throw new \RuntimeException("Cannot create temporary file: {$tmpFile}");
        }

        try {
            foreach ($this->getLine($this->getFilePath()) as $line) {
                $document = \json_decode((string) $line, true);
                
                // Skip invalid JSON or null documents
                if ($document === null) {
                    continue;
                }
                
                // Keep document if it doesn't match the source criteria
                if (($document['sourceType'] ?? '') !== $sourceType || ($document['sourceName'] ?? '') !== $sourceName) {
                    \fwrite($tempHandle, (string) $line);
                }
            }
        } finally {
            \fclose($tempHandle);
        }

        // Replace the original file with the filtered version
        if (file_exists($this->getFilePath())) {
            \unlink($this->getFilePath());
        }
        if (!\rename($tmpFile, $this->getFilePath())) {
            throw new VectorStoreException(self::class." failed to replace original file.");
        }

        return $this;
    }
    
    public function similaritySearch(array $embedding): array
    {
        $topItems = [];

        foreach ($this->getLine($this->getFilePath()) as $document) {
            $document = \json_decode((string) $document, true);

            if ($document === null) {
                continue;
            }
            
            if (empty($document['embedding'])) {
                throw new VectorStoreException("Document with the following content has no embedding: {$document['content']}");
            }
            $dist = VectorSimilarity::cosineDistance($embedding, $document['embedding']);

            $topItems[] = ['dist' => $dist, 'document' => $document];

            \usort($topItems, fn (array $a, array $b): int => $a['dist'] <=> $b['dist']);

            if (\count($topItems) > $this->topK) {
                $topItems = \array_slice($topItems, 0, $this->topK, true);
            }
        }

        return \array_map(function (array $item): Document {
            $itemDoc = $item['document'];
            $document = new Document($itemDoc['content']);
            $document->embedding = $itemDoc['embedding'];
            $document->sourceType = $itemDoc['sourceType'];
            $document->sourceName = $itemDoc['sourceName'];
            $document->id = $itemDoc['id'];
            $document->score = VectorSimilarity::similarityFromDistance($item['dist']);
            $document->metadata = $itemDoc['metadata'] ?? [];

            return $document;
        }, $topItems);
    }

    protected function appendToFile(array $documents): void
    {
        \file_put_contents(
            $this->getFilePath(),
            \implode(\PHP_EOL, \array_map(fn (array $vector) => \json_encode($vector), $documents)).\PHP_EOL,
            \FILE_APPEND
        );
    }

    protected function getLine(string $filename): \Generator
    {
        if (!file_exists($filename)) {
            return;
        }
        
        $f = \fopen($filename, 'r');
        if (!$f) {
            return;
        }

        try {
            while ($line = \fgets($f)) {
                if ($line !== false && trim($line) !== '') {
                    yield $line;
                }
            }
        } finally {
            \fclose($f);
        }
    }

}