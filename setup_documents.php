<?php
namespace App\Neuron;
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/MyChatBot.php';
require_once __DIR__ . '/Providers/MyPDFReader.php';

use NeuronAI\RAG\DataLoader\FileDataLoader;
use App\Neuron\Providers\MyPdfReader;

// Load environment variables
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

// Load documents into vector store with re-indexing
echo "Setting up documents in vector store..." . PHP_EOL;

$kbPath = __DIR__ . '/kb';
echo "Loading from directory: " . $kbPath . PHP_EOL;

$loader = FileDataLoader::for($kbPath);
echo "FileDataLoader created" . PHP_EOL;

$myReader = new MyPdfReader();
echo "MyPdfReader instance created" . PHP_EOL;

$loader->addReader('PDF', $myReader);
$loader->addReader('.pdf', $myReader);
$loader->addReader('pdf', $myReader);
echo "Readers added to loader" . PHP_EOL;

echo "Starting to get documents..." . PHP_EOL;
$documents = $loader->getDocuments();
foreach ($documents as $doc) {
    switch ($doc->sourceName) {
        case 'Drylab.pdf':
            $doc->addMetadata('source', 'drylab');
            $doc->addMetadata('uploaded_at', date('c'));  // ISO8601 timestamp
            break;
        default:
            $doc->addMetadata('source', 'user_manuals');
            $doc->addMetadata('uploaded_at', date('c'));  // ISO8601 timestamp
    }
}
// echo "Documents loaded: " . count($documents) . PHP_EOL;

// Re-index documents in vector store (this will delete old ones and add new ones by source)
echo "Re-indexing documents in vector store..." . PHP_EOL;
$chatbot = MyChatBot::make();
$chatbot->reindexBySource($documents);
echo "Documents re-indexed in vector store!" . PHP_EOL;
echo "Setup complete! You can now use chat.php for questions." . PHP_EOL;
