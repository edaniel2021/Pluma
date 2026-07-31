<?php

namespace App\Domain\Agents\Actions;

use App\Domain\Agents\Enums\AgentMessageRole;
use App\Domain\Agents\Jobs\ProcessAgentMessageJob;
use App\Domain\Agents\Models\AgentMessage;
use App\Domain\Agents\Models\AgentThread;

class SendAgentMessage
{
    public function execute(AgentThread $thread, string $content): AgentMessage
    {
        $message = $thread->messages()->create([
            'role' => AgentMessageRole::User,
            'content' => $content,
        ]);

        ProcessAgentMessageJob::dispatch($thread->id);

        return $message;
    }
}
