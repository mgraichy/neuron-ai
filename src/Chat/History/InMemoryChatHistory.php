<?php

namespace NeuronAI\Chat\History;

use NeuronAI\Chat\Messages\Message;

class InMemoryChatHistory extends AbstractChatHistory
{
    public function __construct(int $contextWindow = 50000, bool $shouldSummarize = false)
    {
        parent::__construct($contextWindow, $shouldSummarize);
    }

    protected function storeMessage(Message $message): ChatHistoryInterface
    {
        return $this;
    }

    public function removeOldestMessage(): ChatHistoryInterface
    {
        return $this;
    }

    protected function clear(): ChatHistoryInterface
    {
        return $this;
    }
}
