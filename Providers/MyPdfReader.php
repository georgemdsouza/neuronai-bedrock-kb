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
        
        // Validate that the text can be properly JSON encoded
        if (!$this->isValidForJson($text)) {
            echo "Skipping file with invalid content for JSON encoding: " . basename($filePath) . PHP_EOL;
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
        // Ensure we have a valid string to work with
        if (empty($text) || $text === null) {
            return '';
        }
        
        // First, try to detect and fix encoding issues
        if (!mb_check_encoding($text, 'UTF-8')) {
            // Try to convert from various encodings
            $encodings = ['ISO-8859-1', 'Windows-1252', 'ASCII'];
            foreach ($encodings as $encoding) {
                if (mb_check_encoding($text, $encoding)) {
                    $converted = mb_convert_encoding($text, 'UTF-8', $encoding);
                    if ($converted !== false && $converted !== null && is_string($converted)) {
                        $text = $converted;
                        break;
                    }
                }
            }
        }
        
        // Force UTF-8 encoding and remove invalid characters
        $converted = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        if ($converted !== false && $converted !== null && is_string($converted)) {
            $text = $converted;
        }
        
        // Ensure text is still valid before proceeding
        if (empty($text) || $text === null || !is_string($text)) {
            return '';
        }
        
        // Remove any remaining invalid UTF-8 sequences
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\x9F]/', '', $text);
        
        // Ensure text is still valid
        if (empty($text) || $text === null || !is_string($text)) {
            return '';
        }
        
        // Remove any other problematic characters that might cause JSON encoding issues
        // Use a safer approach to remove replacement characters
        $text = str_replace("\xEF\xBF\xBD", '', $text); // Remove UTF-8 replacement character
        
        // Ensure text is still valid
        if (empty($text) || $text === null || !is_string($text)) {
            return '';
        }
        
        // Clean up multiple whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Final UTF-8 validation
        $converted = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        if ($converted !== false && $converted !== null && is_string($converted)) {
            $text = $converted;
        }
        
        // Final safety check
        if (empty($text) || $text === null || !is_string($text)) {
            return '';
        }
        
        return trim($text);
    }

    private function isValidForJson(string $text): bool {
        // Check if the text can be properly JSON encoded
        $testArray = ['content' => $text];
        $jsonResult = json_encode($testArray);
        
        if ($jsonResult === false) {
            $error = json_last_error_msg();
            echo "JSON encoding error: " . $error . PHP_EOL;
            return false;
        }
        
        // Also verify UTF-8 validity
        if (!mb_check_encoding($text, 'UTF-8')) {
            echo "Text is not valid UTF-8" . PHP_EOL;
            return false;
        }
        
        return true;
    }
}
