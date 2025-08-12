<?php
namespace App\Neuron;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/Providers/AwsBedrockProvider.php';
require_once __DIR__ . '/Overrides/FileVectorStore.php';
require_once __DIR__ . '/Providers/AwsBedrockEmbeddingsProvider.php';

use NeuronAI\Providers\AIProviderInterface;
use App\Neuron\Providers\AwsBedrockProvider;
use App\Neuron\Providers\AwsBedrockEmbeddingsProvider;
use NeuronAI\RAG\Embeddings\EmbeddingsProviderInterface;
use NeuronAI\RAG\RAG;
use App\Neuron\Overrides\FileVectorStore;
use NeuronAI\RAG\VectorStore\VectorStoreInterface;

class MyChatBot extends RAG
{
    protected function provider(): AIProviderInterface
    {
        // Load credentials from environment variables
        $accessKeyId = $_ENV['AWS_ACCESS_KEY_ID'] ?? getenv('AWS_ACCESS_KEY_ID');
        $secretAccessKey = $_ENV['AWS_SECRET_ACCESS_KEY'] ?? getenv('AWS_SECRET_ACCESS_KEY');
        $model = $_ENV['AWS_MODEL'] ?? 'anthropic.claude-3-5-sonnet-20241022-v2:0';
        $agentId = $_ENV['AWS_AGENT_ID'] ?? null;
        $agentAliasId = $_ENV['AWS_AGENT_ALIAS_ID'] ?? null;
        $region = $_ENV['AWS_REGION'] ?? 'us-east-1';
        
        // Use AWS Bedrock Agent
        return new AwsBedrockProvider(
            accessKeyId: $accessKeyId,
            secretAccessKey: $secretAccessKey,
            model: $model,
            agentId: $agentId,
            agentAliasId: $agentAliasId,
            region: $region
        );
    }
    
    protected function embeddings(): EmbeddingsProviderInterface
    {
        // Load credentials from environment variables
        $accessKeyId = $_ENV['AWS_ACCESS_KEY_ID'] ?? getenv('AWS_ACCESS_KEY_ID');
        $secretAccessKey = $_ENV['AWS_SECRET_ACCESS_KEY'] ?? getenv('AWS_SECRET_ACCESS_KEY');
        $model = $_ENV['AWS_EMBEDDINGS_MODEL'] ?? 'amazon.titan-embed-text-v2:0';
        $region = $_ENV['AWS_REGION'] ?? 'us-east-1';
        
        // Use AWS Bedrock embeddings
        return new AwsBedrockEmbeddingsProvider(
            accessKeyId: $accessKeyId,
            secretAccessKey: $secretAccessKey,
            model: $model,
            region: $region
        );
    }
    
    protected function vectorStore(): VectorStoreInterface
    {
        return new FileVectorStore(
            directory: __DIR__,
            name: 'demo'
        );
    }
}