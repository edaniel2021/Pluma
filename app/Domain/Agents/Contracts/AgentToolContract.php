<?php

namespace App\Domain\Agents\Contracts;

use App\Domain\Agents\Models\AgentThread;
use App\Domain\Organization\Models\Organization;
use App\Models\User;

/**
 * One class per tool the agent can call, registered in
 * AgentConversationService::tools() - the Laravel equivalent of Postiz's
 * Mastra tool set (list channels / generate image / schedule post), but
 * exposed via plain OpenAI function-calling rather than Mastra's framework.
 */
interface AgentToolContract
{
    /**
     * The function name the model sees and calls - must be unique across
     * the registered tool set.
     */
    public function name(): string;

    public function description(): string;

    /**
     * JSON Schema for the "parameters" field of an OpenAI tool definition.
     *
     * @return array<string, mixed>
     */
    public function parameters(): array;

    /**
     * @param  array<string, mixed>  $arguments  Decoded from the model's tool call.
     * @return array<string, mixed> JSON-serializable result fed back to the model.
     */
    public function handle(array $arguments, Organization $organization, User $user, AgentThread $thread): array;
}
