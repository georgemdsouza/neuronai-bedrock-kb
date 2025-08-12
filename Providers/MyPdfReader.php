<?php
namespace App\Neuron\Providers;

use NeuronAI\RAG\DataLoader\ReaderInterface;
use NeuronAI\RAG\Document;

class MyPdfReader implements ReaderInterface {
    public function read(string $filePath): array {
        echo "=== Processing file: " . basename($filePath) . " ===" . PHP_EOL;
        
        $text = shell_exec('pdftotext ' . escapeshellarg($filePath) . ' -') ?: '';
        echo "Raw text length: " . strlen($text) . PHP_EOL;
        
        // Clean the text content
        $text = $this->cleanPdfText($text);
        echo "Cleaned text length: " . strlen($text) . PHP_EOL;
        echo "Text preview: " . substr($text, 0, 100) . "..." . PHP_EOL;
        
        // Skip if text is too short or empty
        if (strlen(trim($text)) < 10) {
            echo "Skipping file with insufficient content: " . basename($filePath) . PHP_EOL;
            return [];
        }
        
        echo "Creating document with " . strlen($text) . " characters" . PHP_EOL;
        return [new Document($text, ['path' => $filePath])];
    }
    
    public static function getText(string $filePath, array $options = []): string {
        echo "=== Testing getText function ===" . PHP_EOL;
        echo "File path: " . $filePath . PHP_EOL;
        
        $text = shell_exec('pdftotext ' . escapeshellarg($filePath) . ' -') ?: '';
        echo "Raw text length: " . strlen($text) . PHP_EOL;
        echo "Raw text (first 200 chars): " . substr($text, 0, 200) . PHP_EOL;
        
        $cleaned = (new self())->cleanPdfText($text);
        echo "Cleaned text length: " . strlen($cleaned) . PHP_EOL;
        echo "Cleaned text (first 200 chars): " . substr($cleaned, 0, 200) . PHP_EOL;
        
        return $cleaned;
    }
    
    private function cleanPdfText(string $text): string {
        // Remove non-UTF8 characters
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        
        // Remove control characters except basic whitespace
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\x9F]/', '', $text);
        
        // Clean up multiple whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }
}
