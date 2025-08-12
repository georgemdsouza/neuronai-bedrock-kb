<?php
namespace App\Neuron\Overrides;

use NeuronAI\Exceptions\VectorStoreException;
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

}