<?php
namespace App\Neuron\Providers;

use NeuronAI\RAG\Embeddings\AbstractEmbeddingsProvider;
use NeuronAI\RAG\Document;
use Aws\BedrockRuntime\BedrockRuntimeClient;
use Aws\Exception\AwsException;

class AwsBedrockEmbeddingsProvider extends AbstractEmbeddingsProvider
{
    protected BedrockRuntimeClient $client;
    protected string $model;
    protected string $region;

    public function __construct(
        string $accessKeyId,
        string $secretAccessKey,
        string $model = 'amazon.titan-embed-text-v2:0',
        string $region = 'us-east-1'
    ) {
        $this->model = $model;
        $this->region = $region;
        
        $this->client = new BedrockRuntimeClient([
            'version' => 'latest',
            'region' => $region,
            'credentials' => [
                'key' => $accessKeyId,
                'secret' => $secretAccessKey,
            ]
        ]);
    }

    public function embedText(string $text): array
    {
        try {
            // Clean and validate the text before processing
            $text = $this->sanitizeText($text);
            
            // Skip empty or very short text
            if (strlen(trim($text)) < 3) {
                return [];
            }
            
            $payload = $this->buildPayload($text);
            
            // Validate that JSON encoding works
            $jsonBody = json_encode($payload);
            if ($jsonBody === false) {
                throw new \Exception("Failed to encode payload to JSON. Text may contain invalid characters.");
            }
            
            $result = $this->client->invokeModel([
                'modelId' => $this->model,
                'body' => $jsonBody,
                'contentType' => 'application/json',
                'accept' => 'application/json'
            ]);
            
            $response = json_decode($result['body']->getContents(), true);
            
            return $this->extractEmbedding($response);
            
        } catch (AwsException $e) {
            throw new \Exception("AWS Bedrock embeddings error: " . $e->getMessage(), 0, $e);
        }
    }

    protected function buildPayload(string $text): array
    {
        // Different models have different payload structures
        return match (true) {
            str_contains($this->model, 'titan') => [
                'inputText' => $text
            ],
            str_contains($this->model, 'cohere') => [
                'texts' => [$text],
                'input_type' => 'search_document',
                'truncate' => 'NONE'
            ],
            str_contains($this->model, 'sentence-transformers') => [
                'texts' => [$text]
            ],
            default => [
                'inputText' => $text
            ]
        };
    }

    protected function extractEmbedding(array $response): array
    {
        // Different models return embeddings in different formats
        return match (true) {
            str_contains($this->model, 'titan') => $response['embedding'] ?? [],
            str_contains($this->model, 'cohere') => $response['embeddings'][0] ?? [],
            str_contains($this->model, 'sentence-transformers') => $response['embeddings'][0] ?? [],
            default => $response['embedding'] ?? []
        };
    }

    /**
     * Sanitize text content to ensure it's valid UTF-8 and can be JSON encoded
     */
    protected function sanitizeText(string $text): string
    {
        // Convert to UTF-8 if it's not already
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }
        
        // Remove non-printable characters except basic whitespace (\t, \n, \r, space)
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\x9F]/u', '', $text);
        
        // Replace multiple whitespace with single space
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Trim whitespace
        $text = trim($text);
        
        // If text is still problematic for JSON encoding, use aggressive cleanup
        if (json_encode(['test' => $text]) === false) {
            // Keep only ASCII printable characters and basic whitespace
            $text = preg_replace('/[^\x20-\x7E\n\r\t]/', '', $text);
            $text = trim($text);
        }
        
        return $text;
    }

    public function embedDocuments(array $documents): array
    {
        // For batch processing, we'll process them one by one
        // Some models support batch processing, but for simplicity we'll do individual calls
        $processedCount = 0;
        $errorCount = 0;
        
        foreach ($documents as $index => $document) {
            try {
                $documents[$index] = $this->embedDocument($document);
                $processedCount++;
                
                // Log progress every 100 documents
                if ($processedCount % 100 === 0) {
                    echo "Processed {$processedCount} documents..." . PHP_EOL;
                }
            } catch (\Exception $e) {
                $errorCount++;
                echo "Error processing document {$index}: " . $e->getMessage() . PHP_EOL;
                
                // Skip this document but continue with others
                // Set empty embedding so the document structure is preserved
                $document->embedding = [];
                $documents[$index] = $document;
            }
        }
        
        echo "Processing complete: {$processedCount} successful, {$errorCount} errors" . PHP_EOL;
        
        return $documents;
    }
}
