<?php

namespace NeuronAI\Chat\History;

use NeuronAI\Chat\Messages\Message;
use NeuronAI\Exceptions\ChatHistoryException;

class FileChatHistory extends AbstractChatHistory
{
    public function __construct(
        protected string $directory,
        protected string $key,
        int $contextWindow = 50000,
        protected string $prefix = 'neuron_',
        protected string $ext = '.chat',
        bool $shouldSummarize = false
    ) {
        parent::__construct($contextWindow, $shouldSummarize);

        if (!\is_dir($this->directory)) {
            throw new ChatHistoryException("Directory '{$this->directory}' does not exist");
        }

        $this->init();
    }

    protected function init(): void
    {
        if (\is_file($this->getFilePath())) {
            $messages = \json_decode(\file_get_contents($this->getFilePath()), true) ?? [];
            $this->history = $this->deserializeMessages($messages);
        }
    }

    protected function getFilePath(): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . $this->prefix.$this->key.$this->ext;
    }

    protected function storeMessage(Message $message): ChatHistoryInterface
    {
        $this->updateFile();
        return $this;
    }

    public function removeOldestMessage(): ChatHistoryInterface
    {
        $this->updateFile();
        return $this;
    }

    protected function clear(): ChatHistoryInterface
    {
        if (!\unlink($this->getFilePath())) {
            throw new ChatHistoryException("Unable to delete file '{$this->getFilePath()}'");
        }
        return $this;
    }

    protected function updateFile(): void
    {
        \file_put_contents($this->getFilePath(), \json_encode($this->jsonSerialize()), LOCK_EX);
    }
}
