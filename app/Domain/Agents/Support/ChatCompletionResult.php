<?php

namespace App\Domain\Agents\Support;

/**
 * Provider-neutral result of one chat-completion call, normalized from
 * whichever provider produced it - see ChatCompletionContract.
 */
readonly class ChatCompletionResult
{
    /**
     * @param  array<int, ChatToolCall>  $toolCalls
     */
    public function __construct(
        public ?string $content,
        public array $toolCalls,
    ) {}
}
