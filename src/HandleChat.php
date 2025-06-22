<?php

namespace NeuronAI;

use GuzzleHttp\Promise\PromiseInterface;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\ToolCallMessage;
use NeuronAI\Exceptions\AgentException;
use NeuronAI\Observability\Events\AgentError;
use NeuronAI\Observability\Events\MessageSaved;
use NeuronAI\Observability\Events\MessageSaving;
use NeuronAI\Observability\Events\InferenceStart;
use NeuronAI\Observability\Events\InferenceStop;

trait HandleChat
{
    /**
     * Execute the chat.
     *
     * @param Message|array $messages
     * @return Message
     * @throws \Throwable
     */
    public function chat(Message|array $messages): Message
    {
        $message = $this->chatAsync($messages)->wait();

        if (
            $this->chatHistory->shouldSummarize() &&
            !empty($this->chatHistory->getLastMessage(isSummary: true)->getContent())
        ) {
            $summary = $this->summarizeAsync()->wait();
            $message->setSummaryMessage($summary->getContent());
        }

        return $message;
    }

    public function chatAsync(Message|array $messages): PromiseInterface
    {
        $this->notify('chat-start');

        $this->fillChatHistory($messages);

        $tools = $this->bootstrapTools();

        $this->notify(
            'inference-start',
            new InferenceStart($this->resolveChatHistory()->getLastMessage())
        );

        return $this->resolveProvider()
            ->systemPrompt($this->resolveInstructions())
            ->setTools($tools)
            ->chatAsync(
                $this->resolveChatHistory()->getMessages()
            )->then($this->getAgentClosure(), $this->getAgentExceptionClosure());
    }

    protected function summarizeAsync(): PromiseInterface
    {
        $this->notify('chat-start');

        $this->fillChatHistory($this->chatHistory->getLastMessage(isSummary: true));

        $this->notify(
            'inference-start',
            new InferenceStart($this->chatHistory->getLastMessage(isSummary: true))
        );

        return $this->resolveProvider()
            ->systemPrompt($this->chatHistory->getSummaryPrompt())
            ->chatAsync($this->chatHistory->getPreSummaryHistory())
            ->then($this->getAgentClosure(isSummary: true), $this->getAgentExceptionClosure());
    }

    protected function getAgentClosure(bool $isSummary = false): Callable
    {
        return function (Message $response) use ($isSummary) {
            $this->notify(
                'inference-stop',
                new InferenceStop($this->resolveChatHistory()->getLastMessage(isSummary: $isSummary), $response)
            );

            if ($response instanceof ToolCallMessage) {
                $toolCallResult = $this->executeTools($response);
                return $this->chatAsync([$response, $toolCallResult]);
            } else {
                $this->notify('message-saving', new MessageSaving($response));
                $this->resolveChatHistory()->addMessage($response);
                $this->notify('message-saved', new MessageSaved($response));
            }

            $this->notify('chat-stop');
            return $response;
        };
    }

    protected function getAgentExceptionClosure(): Callable
    {
        return function (\Throwable $exception) {
            $this->notify('error', new AgentError($exception));
            throw new AgentException($exception->getMessage(), (int)$exception->getCode(), $exception);
        };
    }
}
