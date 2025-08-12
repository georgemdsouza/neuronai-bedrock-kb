<?php
namespace App\Neuron\Providers;

use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\MessageMapperInterface;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Tools\ToolInterface;
use GuzzleHttp\Promise\PromiseInterface;
use Aws\Bedrock\BedrockClient;
use Aws\BedrockRuntime\BedrockRuntimeClient;
use Aws\BedrockAgentRuntime\BedrockAgentRuntimeClient;
use Aws\Exception\AwsException;

class AwsBedrockProvider implements AIProviderInterface
{
    protected BedrockRuntimeClient $runtimeClient;
    protected BedrockAgentRuntimeClient $agentClient;
    protected BedrockClient $bedrockClient;
    protected string $model;
    protected string $agentId;
    protected string $agentAliasId;
    protected string $region;
    protected ?string $system = null;
    protected array $tools = [];

    public function __construct(
        string $accessKeyId,
        string $secretAccessKey,
        string $agentId,
        string $agentAliasId,
        string $model = 'anthropic.claude-3-sonnet-20240229-v1:0',
        string $region = 'us-east-1'
    ) {
        $this->model = $model;
        $this->agentId = $agentId;
        $this->region = $region;
        $this->agentAliasId = $agentAliasId;
        
        // Client for regular model invocation
        $this->runtimeClient = new BedrockRuntimeClient([
            'version' => 'latest',
            'region' => $region,
            'credentials' => [
                'key' => $accessKeyId,
                'secret' => $secretAccessKey,
            ]
        ]);
        
        // Client for listing agents and getting agent info
        $this->bedrockClient = new BedrockClient([
            'version' => 'latest',
            'region' => $region,
            'credentials' => [
                'key' => $accessKeyId,
                'secret' => $secretAccessKey,
            ]
        ]);
        
        // Client for agent invocation (if agentId is provided)
        if ($this->agentId) {
            $this->agentClient = new BedrockAgentRuntimeClient([
                'version' => 'latest',
                'region' => $region,
                'credentials' => [
                    'key' => $accessKeyId,
                    'secret' => $secretAccessKey,
                ]
            ]);
        }
    }

    public function systemPrompt(?string $prompt): AIProviderInterface
    {
        $this->system = $prompt;
        return $this;
    }

    public function setTools(array $tools): AIProviderInterface
    {
        $this->tools = $tools;
        return $this;
    }

    public function messageMapper(): MessageMapperInterface
    {
        return new class implements MessageMapperInterface {
            public function toProvider(array $messages): array { return $messages; }
            public function fromProvider(array $response): Message { return new AssistantMessage($response['content'] ?? ''); }
        };
    }

    public function chat(array $messages): Message
    {
        try {
            // If agentId is set, use agent invocation
            if ($this->agentId) {
                return $this->invokeAgent($messages);
            }
            
            // Otherwise, use regular model invocation
            return $this->invokeModel($messages);
            
        } catch (AwsException $e) {
            throw new \Exception("AWS Bedrock error: " . $e->getMessage(), 0, $e);
        }
    }

    protected function invokeAgent(array $messages): Message
    {
        // Prepare input for agent
        $input = $this->buildAgentInput($messages);
        
        // Use invokeAgent with the working structure discovered
        $result = $this->agentClient->invokeAgent([
            'agentId' => $this->agentId,
            'agentAliasId' => $this->agentAliasId, // Use the working alias ID
            'sessionId' => uniqid('session_'),
            'inputText' => $input
        ]);
        
        // Handle the streaming response
        $responseContent = '';
        foreach ($result['completion'] as $event) {
            if (isset($event['chunk']['bytes'])) {
                $responseContent .= $event['chunk']['bytes'];
            }
        }
        
        return new AssistantMessage($responseContent ?: 'Agent response received');
    }

    protected function invokeModel(array $messages): Message
    {
        $payload = $this->buildChatPayload($messages);
        
        $result = $this->runtimeClient->invokeModel([
            'modelId' => $this->model,
            'body' => json_encode($payload),
            'contentType' => 'application/json',
            'accept' => 'application/json'
        ]);
        
        $response = json_decode($result['body']->getContents(), true);
        
        return $this->extractResponse($response);
    }

    protected function buildAgentInput(array $messages): string
    {
        $input = '';
        
        // Add system message if present
        if ($this->system) {
            $input .= "System: {$this->system}\n\n";
        }
        
        // Combine all messages
        foreach ($messages as $message) {
            $input .= "{$message->getRole()}: {$message->getContent()}\n";
        }
        
        return trim($input);
    }

    protected function buildChatPayload(array $messages): array
    {
        $formattedMessages = [];
        
        // Add system message if present
        if ($this->system) {
            $formattedMessages[] = [
                'role' => 'system',
                'content' => $this->system
            ];
        }
        
        // Format messages for the specific model
        if (str_contains($this->model, 'anthropic')) {
            // Claude format
            foreach ($messages as $message) {
                $formattedMessages[] = [
                    'role' => $message->getRole(),
                    'content' => $message->getContent()
                ];
            }
            
            return [
                'anthropic_version' => 'bedrock-2023-05-31',
                'max_tokens' => 4096,
                'messages' => $formattedMessages
            ];
        } else {
            // Amazon Titan format
            $text = '';
            foreach ($messages as $message) {
                $text .= $message->getContent() . "\n";
            }
            
            return [
                'inputText' => trim($text),
                'textGenerationConfig' => [
                    'maxTokenCount' => 4096,
                    'stopSequences' => [],
                    'temperature' => 0.7,
                    'topP' => 1.0
                ]
            ];
        }
    }

    protected function extractResponse(array $response): Message
    {
        // Handle Claude response format
        if (isset($response['content'][0]['text'])) {
            return new AssistantMessage($response['content'][0]['text']);
        }
        
        // Handle Amazon Titan response format
        if (isset($response['results'][0]['outputText'])) {
            return new AssistantMessage($response['results'][0]['outputText']);
        }
        
        // Fallback
        return new AssistantMessage($response['content'] ?? 'No response generated');
    }

    public function chatAsync(array $messages): PromiseInterface
    {
        return \GuzzleHttp\Promise\Create::promiseFor(null)
            ->then(function() use ($messages) {
                return $this->chat($messages);
            });
    }

    public function stream(array|string $messages, callable $executeToolsCallback): \Generator
    {
        // Simple streaming implementation
        $response = $this->chat(is_array($messages) ? $messages : [new UserMessage($messages)]);
        yield $response->getContent();
    }

    public function structured(array $messages, string $class, array $response_schema): Message
    {
        // Simple structured output implementation
        return $this->chat($messages);
    }

    public function setClient($client): AIProviderInterface
    {
        // This method expects a GuzzleHttp\Client, but we're using AWS SDK
        // We'll implement a compatibility layer if needed
        return $this;
    }

    // Helper method to list available agents
    public function listAgents(): array
    {
        try {
            $result = $this->bedrockClient->listAgents();
            return $result['agentSummaries'] ?? [];
        } catch (AwsException $e) {
            throw new \Exception("Error listing agents: " . $e->getMessage(), 0, $e);
        }
    }

    // Helper method to get agent details
    public function getAgent(string $agentId = null): array
    {
        $agentId = $agentId ?: $this->agentId;
        if (!$agentId) {
            throw new \Exception("No agent ID specified.");
        }

        try {
            $result = $this->bedrockClient->getAgent([
                'agentId' => $agentId
            ]);
            return $result->toArray();
        } catch (AwsException $e) {
            throw new \Exception("Error getting agent: " . $e->getMessage(), 0, $e);
        }
    }
}
