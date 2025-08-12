<?php
namespace App\Neuron;
require_once __DIR__ . '/vendor/autoload.php';

echo "PDF Knowledge Base Setup" . PHP_EOL;
echo str_repeat("=", 30) . PHP_EOL;
echo PHP_EOL;
echo "To set up documents (run once):" . PHP_EOL;
echo "  php setup_documents.php" . PHP_EOL;
echo PHP_EOL;
echo "To chat with your knowledge base:" . PHP_EOL;
echo "  php chat.php" . PHP_EOL;
echo PHP_EOL;
echo "Documents will be loaded into the vector store and persist between sessions." . PHP_EOL;