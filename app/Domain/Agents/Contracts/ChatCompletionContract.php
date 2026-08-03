<?php

namespace App\Domain\Agents\Contracts;

use App\Domain\Agents\Support\ChatCompletionResult;

/**
 * The provider-swap point behind AgentConversationService - implemented by
 * OpenAiService and GeminiService (config('agents.chat_provider') picks
 * which is bound, see AppServiceProvider::register()). Each implementation
 * owns translating our internal OpenAI-shaped wire messages/tools into
 * whatever its own API actually expects, and normalizing its response back
 * into ChatCompletionResult - AgentConversationService never sees an
 * SDK-specific response shape.
 */
interface ChatCompletionContract
{
    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     */
    public function chat(array $messages, array $tools = []): ChatCompletionResult;
}
