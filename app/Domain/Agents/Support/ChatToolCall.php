<?php

namespace App\Domain\Agents\Support;

/**
 * Provider-neutral shape for a single requested tool call, normalized from
 * whichever chat provider produced it (OpenAI's tool_calls, Gemini's
 * functionCall parts) so AgentConversationService never sees SDK-specific
 * response objects.
 */
readonly class ChatToolCall
{
    public function __construct(
        public string $id,
        public string $name,
        public string $argumentsJson,
    ) {}
}
