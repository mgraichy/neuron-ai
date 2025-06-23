# Create Full-Featured AI Agents As Standalone Components In Any PHP Application

[![Latest Stable Version](https://poser.pugx.org/inspector-apm/neuron-ai/v/stable)](https://packagist.org/packages/inspector-apm/neuron-ai)
[![Total Downloads](http://poser.pugx.org/inspector-apm/neuron-ai/downloads)](https://packagist.org/packages/inspector-apm/neuron-ai)

> Before moving on, support the community giving a GitHub star ⭐️ [to the original repo](https://github.com/inspector-apm/neuron-ai/). Thank you!

## Use of Summaries

1. Register a chat history with enabled summarizing:

    ```php
    class MyAgent extends Agent
    {
        protected function chatHistory()
        {
            return new InMemoryChatHistory(
                contextWindow: 50000,
                shouldSummarize: true
            );
        }
    }
    ```

    * This summarizes only when your tokens exceed the context window.


2. Send the summary to the front end along with the LLM's message:

    ```php
    $response = MyAgent::make($user)->chat(
        new UserMessage("Hello!")
    );

    $content['content'] = $response->getContent();
    if ($summary = $response->getSummaryMessage()) {
        $content['summary'] = $summary;
    }

    return ['data' => $content];
    ```

    * The summary is not intended for display to the user.
    * Instead, include the summary with only the user's current (i.e., last) message in your next frontend call to the LLM API.
    * This should reduce token usage for further calls to the LLM, while still giving the LLM enough context to answer appropriately.

## Reference to Added or Modified Classes and Methods
1. [**src/HandleChat.php**](https://github.com/mgraichy/neuron-ai/blob/main/src/HandleChat.php)
    * `chat(Message|array $messages): Message`
    * `chatAsync(Message|array $messages): PromiseInterface`
    * `summarizeAsync(): PromiseInterface`
    * The following two methods extract the callbacks so they can be repeatedly used throughout the class:
        * `getAgentClosure(bool $isSummary = false): callable`
        * `getAgentExceptionClosure(): callable`
2. [**src/Chat/History/AbstractChatHistory.php**](https://github.com/mgraichy/neuron-ai/blob/main/src/Chat/History/AbstractChatHistory.php)
    * `$preSummaryHistory = [];` and `__construct(protected int $contextWindow = 50000, protected bool $shouldSummarize = false)`
    * `getLastMessage(?bool $isSummary = false): Message`
    * `cutHistoryToContextWindow(): void`
    * `shouldSummarize(): bool`
    * `getSummaryPrompt(?string $prompt = null): ?string`
    * `getPreSummaryHistory(): array`
    * `formatPreSummaryMessages(): void`
3. [**src/Chat/History/FileChatHistory.php**](https://github.com/mgraichy/neuron-ai/blob/main/src/Chat/History/FileChatHistory.php)
    * `public function __construct(/*...*/, bool $shouldSummarize = false)`
4. [**src/Chat/History/InMemoryChatHistory.php**](https://github.com/mgraichy/neuron-ai/blob/main/src/Chat/History/InMemoryChatHistory.php)
    * `__construct(int $contextWindow = 50000, bool $shouldSummarize = false)`
5. [**src/Chat/Messages/Message.php**](https://github.com/mgraichy/neuron-ai/blob/main/src/Chat/Messages/Message.php)
    * `protected ?string $summary = null;`
    * `getSummaryMessage(): ?string`
    * `setSummaryMessage(string $summarized): void`
6. [**tests/ChatHistoryTest.php**](https://github.com/mgraichy/neuron-ai/blob/main/tests/ChatHistoryTest.php)
    * `test_chat_history_instance_with_summary()`
    * `test_chat_history_summarize()`

[**Video Tutorial**](https://www.youtube.com/watch?v=fJSX8wWIDO8)

[![Neuron & Inspector](./docs/images/youtube.png)](https://www.youtube.com/watch?v=fJSX8wWIDO8)

---

## Requirements

- PHP: ^8.1

## Official documentation

**[Go to the official documentation](https://neuron.inspector.dev/)**

## Guides & Tutorials

Check out the technical guides and tutorials archive to learn how to start creating your AI Agents with Neuron
https://docs.neuron-ai.dev/resources/guides-and-tutorials.

## Neuron AI Examples

- [Install](#install)
- [Create an Agent](#create)
- [Talk to the Agent](#talk)
- [Monitoring](#monitoring)
- [Supported LLM Providers](#providers)
- [Tools & Function Calls](#tools)
- [MCP server connector](#mcp)
- [Implement RAG systems](#rag)
- [Structured Output](#structured)
- [Official Documentation](#documentation)

<a name="install">

## Install

Install the latest version of the original package:

```
composer require inspector-apm/neuron-ai
```

<a name="create">

## Create an Agent

Neuron provides you with the Agent class you can extend to inherit the main features of the framework
and create fully functional agents. This class automatically manages some advanced mechanisms for you, such as memory,
tools and function calls, up to the RAG systems. You can go deeper into these aspects in the [documentation](https://docs.neuron-ai.dev).
In the meantime, let's create the first agent, extending the `NeuronAI\Agent` class:

```php
<?php

namespace App\Neuron;

use NeuronAI\Agent;
use NeuronAI\SystemPrompt;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Anthropic\Anthropic;

class DataAnalystAgent extends Agent
{
    public function provider(): AIProviderInterface
    {
        return new Anthropic(
            key: 'ANTHROPIC_API_KEY',
            model: 'ANTHROPIC_MODEL',
        );
    }

    public function instructions(): string
    {
        return new SystemPrompt(
            background: [
                "You are a data analyst expert in creating reports from SQL databases."
            ]
        );
    }
}
```

The `SystemPrompt` class is designed to take your base instructions and build a consistent prompt for the underlying model
reducing the effort for prompt engineering.

<a name="talk">

## Talk to the Agent

Send a prompt to the agent to get a response from the underlying LLM:

```php

$agent = DataAnalystAgent::make();


$response = $agent->chat(
    new UserMessage("Hi, I'm Valerio. Who are you?")
);
echo $response->getContent();
// I'm a data analyst. How can I help you today?


$response = $agent->chat(
    new UserMessage("Do you know my name?")
);
echo $response->getContent();
// Your name is Valerio, as you said in your introduction.
```

As you can see in the example above, the Agent automatically has memory of the ongoing conversation. Learn more about memory in the [documentation](https://docs.neuron-ai.dev/chat-history-and-memory).

<a name="monitoring">

## Monitoring

Integrating AI Agents into your application you’re not working only with functions and deterministic code,
you program your agent also influencing probability distributions. Same input ≠ output.
That means reproducibility, versioning, and debugging become real problems.

Many of the Agents you build with NeuronAI will contain multiple steps with multiple invocations of LLM calls,
tool usage, access to external memories, etc. As these applications get more and more complex, it becomes crucial
to be able to inspect what exactly your agent is doing and why.

Why is the model taking certain decisions? What data is the model reacting to? Prompting is not programming
in the common sense. No static types, small changes break output, long prompts cost latency,
and no two models behave exactly the same with the same prompt.

The best way to do this is with [Inspector](https://inspector.dev). After you sign up,
make sure to set the `INSPECTOR_INGESTION_KEY` variable in the application environment file to start monitoring:

```dotenv
INSPECTOR_INGESTION_KEY=fwe45gtxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

After configuring the environment variable, you will see the agent execution timeline in your Inspector dashboard.

![](./docs/images/neuron-observability.avif)

Learn more about Monitoring in the [documentation](https://docs.neuron-ai.dev/advanced/observability).

<a name="providers">

## Supported LLM Providers

With NeuronAI, you can switch between LLM providers with just one line of code, without any impact on your agent implementation.
Supported providers:

- Anthropic
- Ollama (also available as an [embeddings provider](https://docs.neuron-ai.dev/components/embeddings-provider#ollama))
- OpenAI (also available as an [embeddings provider](https://docs.neuron-ai.dev/components/embeddings-provider#openai))
- OpenAI on Azure
- Gemini
- HuggingFace

<a name="tools">

## Tools & Toolkits

You can add abilities to your agent to perform concrete tasks:

```php
<?php

namespace App\Neuron;

use NeuronAI\Agent;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\SystemPrompt;
use NeuronAI\Tools\ToolProperty;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\Toolkits\MySQL\MySQLToolkit;

class DataAnalystAgent extends Agent
{
    public function provider(): AIProviderInterface
    {
        return new Anthropic(
            key: 'ANTHROPIC_API_KEY',
            model: 'ANTHROPIC_MODEL',
        );
    }

    public function instructions(): string
    {
        return new SystemPrompt(
            background: [
                "You are a data analyst expert in creating reports from SQL databases."
            ]
        );
    }

    public function tools(): array
    {
        return [
            MySQLToolkit:make(
                \DB::connection()->getPdo()
            ),
        ];
    }
}
```

Learn more about Tools in the [documentation](https://docs.neuron-ai.dev/tools-and-function-calls).

<a name="mcp">

## MCP server connector

Instead of implementing tools manually, you can connect tools exposed by an MCP server with the `McpConnector` component:

```php
<?php

namespace App\Neuron;

use NeuronAI\Agent;
use NeuronAI\MCP\McpConnector;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\Tools\ToolProperty;
use NeuronAI\Tools\Tool;

class DataAnalystAgent extends Agent
{
    public function provider(): AIProviderInterface
    {
        ...
    }

    public function instructions(): string
    {
        ...
    }

    public function tools(): array
    {
        return [
            // Connect to an MCP server
            ...McpConnector::make([
                'command' => 'npx',
                'args' => ['-y', '@modelcontextprotocol/server-everything'],
            ])->tools(),
        ];
    }
}
```

Learn more about MCP connector in the [documentation](https://docs.neuron-ai.dev/advanced/mcp-servers-connection).

<a name="rag">

## Implement RAG systems

For RAG use case, you must extend the `NeuronAI\RAG\RAG` class instead of the default Agent class.

To create a RAG you need to attach some additional components other than the AI provider, such as a `vector store`,
and an `embeddings provider`.

Here is an example of a RAG implementation:

```php
<?php

namespace App\Neuron;

use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\RAG\Embeddings\EmbeddingsProviderInterface;
use NeuronAI\RAG\Embeddings\VoyageEmbeddingProvider;
use NeuronAI\RAG\RAG;
use NeuronAI\RAG\VectorStore\PineconeVectorStore;
use NeuronAI\RAG\VectorStore\VectorStoreInterface;

class MyChatBot extends RAG
{
    public function provider(): AIProviderInterface
    {
        return new Anthropic(
            key: 'ANTHROPIC_API_KEY',
            model: 'ANTHROPIC_MODEL',
        );
    }

    public function embeddings(): EmbeddingsProviderInterface
    {
        return new VoyageEmbeddingProvider(
            key: 'VOYAGE_API_KEY',
            model: 'VOYAGE_MODEL'
        );
    }

    public function vectorStore(): VectorStoreInterface
    {
        return new PineconeVectorStore(
            key: 'PINECONE_API_KEY',
            indexUrl: 'PINECONE_INDEX_URL'
        );
    }
}
```

Learn more about RAG in the [documentation](https://docs.neuron-ai.dev/rag).

<a name="structured">

## Structured Output
For many applications, such as chatbots, Agents need to respond to users directly in natural language.
However, there are scenarios where we need Agents to understand natural language, but output in a structured format.

One common use-case is extracting data from text to insert into a database or use with some other downstream system.
This guide covers a few strategies for getting structured outputs from the agent.

```php
use App\Neuron\MyAgent;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\StructuredOutput\SchemaProperty;

/*
 * Define the output structure as a PHP class.
 */
class Person
{
    #[SchemaProperty(description: 'The user name')]
    public string $name;

    #[SchemaProperty(description: 'What the user love to eat')]
    public string $preference;
}

// Talk to the agent requiring the structured output
$person = MyAgent::make()->structured(
    new UserMessage("I'm John and I like pizza!"),
    Person::class
);

echo $person->name ' like '.$person->preference;
// John like pizza
```

Learn more about Structured Output on the [documentation](https://docs.neuron-ai.dev/advanced/structured-output).

<a name="documentation">

## Official documentation

**[Go to the official documentation](https://neuron.inspector.dev/)**


