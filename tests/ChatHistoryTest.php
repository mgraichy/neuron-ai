<?php

namespace NeuronAI\Tests;

use NeuronAI\Chat\History\AbstractChatHistory;
use NeuronAI\Chat\History\FileChatHistory;
use NeuronAI\Chat\History\InMemoryChatHistory;
use NeuronAI\Chat\Messages\Usage;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\UserMessage;
use PHPUnit\Framework\TestCase;

class ChatHistoryTest extends TestCase
{
    public function test_chat_history_instance()
    {
        $history = new InMemoryChatHistory();
        $this->assertInstanceOf(AbstractChatHistory::class, $history);
    }

    public function test_chat_history_add_message()
    {
        $history = new InMemoryChatHistory();
        $history->addMessage(new UserMessage('Hello!'));
        $this->assertCount(1, $history->getMessages());
    }

    public function test_chat_history_truncate()
    {
        $history = new InMemoryChatHistory(300);
        $this->assertEquals(300, $history->getFreeMemory());

        $message = new UserMessage('Hello!');
        $message->setUsage(new Usage(100, 100));
        $history->addMessage($message);
        $this->assertEquals(100, $history->getFreeMemory());
        $this->assertEquals(200, $history->calculateTotalUsage());

        $message = new UserMessage('Hello!');
        $message->setUsage(new Usage(300, 100));
        $history->addMessage($message);
        $this->assertEquals(0, $history->getFreeMemory());
        $this->assertEquals(300, $history->calculateTotalUsage());
        $this->assertCount(1, $history->getMessages());
    }

    public function test_chat_history_clear()
    {
        $history = new InMemoryChatHistory();
        $history->addMessage(new UserMessage('Hello!'));
        $history->addMessage(new UserMessage('Hello2!'));
        $history->flushAll();
        $this->assertCount(0, $history->getMessages());
    }

    public function test_file_chat_history()
    {
        $history = new FileChatHistory(__DIR__, 'test');
        $this->assertFileDoesNotExist(__DIR__.DIRECTORY_SEPARATOR.'neuron_test.chat');

        $history->addMessage(new UserMessage('Hello!'));
        $this->assertFileExists(__DIR__.DIRECTORY_SEPARATOR.'neuron_test.chat');
        $this->assertCount(1, $history->getMessages());

        $history->flushAll();
        $this->assertFileDoesNotExist(__DIR__.DIRECTORY_SEPARATOR.'neuron_test.chat');
        $this->assertCount(0, $history->getMessages());
    }

    public function test_file_chat_history_init()
    {
        $history = new FileChatHistory(__DIR__, 'test');
        $history->addMessage(new UserMessage('Hello!'));
        $this->assertFileExists(__DIR__.DIRECTORY_SEPARATOR.'neuron_test.chat');

        $history = new FileChatHistory(__DIR__, 'test');
        $this->assertCount(1, $history->getMessages());
        $history->flushAll();
    }

    public function test_tokens_calculation()
    {
        $message = new UserMessage('Hello!');
        $message->setUsage(new Usage(100, 100));

        $history = new InMemoryChatHistory(300);
        $history->addMessage($message);
        $this->assertEquals(200, $history->calculateTotalUsage());
        $this->assertEquals(100, $history->getFreeMemory());

        $history->addMessage($message);
        $this->assertEquals(100, $history->getFreeMemory());
        $this->assertEquals(200, $history->calculateTotalUsage());
    }

    public function test_chat_history_instance_with_summary()
    {
        $history = new InMemoryChatHistory(contextWindow: 50000, shouldSummarize: true);
        $this->assertInstanceOf(AbstractChatHistory::class, $history);
    }

    public function test_chat_history_summarize()
    {
        $history = new InMemoryChatHistory(300, shouldSummarize: true);
        $this->assertEquals(true, $history->shouldSummarize());
        $this->assertEquals(300, $history->getFreeMemory());

        // Prior to filling in $history->preSummaryHistory:
        $this->assertEquals([], $history->getPreSummaryHistory());
        $this->assertInstanceOf(AssistantMessage::class, $history->getLastMessage(isSummary: true));

        $message = new UserMessage('Hello!');
        $message->setUsage(new Usage(100, 200));
        $history->addMessage($message);
        $assistantMessage = new AssistantMessage('I\'ll trigger formatPreSummaryMessages()');
        $assistantMessage->setUsage(new Usage(200, 300));
        $history->addMessage($assistantMessage);

        $this->assertStringStartsWith(
            'Summarize the conversation below',
            $history->getLastMessage(isSummary: true)->getContent()
        );
    }
}
