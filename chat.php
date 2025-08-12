<?php
namespace App\Neuron;
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/MyChatBot.php';

use NeuronAI\Chat\Messages\UserMessage;

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

// Simple chat interface - documents already loaded in vector store
echo "Chat with your PDF knowledge base (type 'quit' to exit)" . PHP_EOL;
echo "Documents are already loaded in the vector store." . PHP_EOL;
echo str_repeat("-", 50) . PHP_EOL;

$chatbot = MyChatBot::make();

while (true) {
    echo "You: ";
    $input = trim(fgets(STDIN));
    
    if (strtolower($input) === 'quit') {
        echo "Goodbye!" . PHP_EOL;
        break;
    }
    
    if (empty($input)) {
        continue;
    }
    
    try {
        echo "Processing..." . PHP_EOL;
        $response = $chatbot->chat([
            new UserMessage($input)
        ]);
        
        echo "Assistant: " . $response->getContent() . PHP_EOL;
        echo str_repeat("-", 50) . PHP_EOL;
        
    } catch (\Exception $e) {
        echo "Error: " . $e->getMessage() . PHP_EOL;
    }
}
