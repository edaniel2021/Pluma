<?php

namespace App\Domain\Agents\Events;

use App\Domain\Agents\Models\AgentMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired by AgentMessage::booted() on every create - upgrades the chat UI
 * (App\Livewire\Agents\Chat) from Phase 6's wire:poll to real-time via
 * Reverb. Broadcasts synchronously (ShouldBroadcastNow, not ShouldBroadcast)
 * since message creation already happens inside a queued job
 * (ProcessAgentMessageJob) or a request (SendAgentMessage) - queuing the
 * broadcast too would just add a redundant hop and delay the "real-time"
 * part of real-time.
 */
class AgentMessageCreated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public AgentMessage $message)
    {
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('agent-thread.'.$this->message->agent_thread_id)];
    }

    public function broadcastAs(): string
    {
        return 'AgentMessageCreated';
    }

    /**
     * Deliberately minimal - the Chat component's listener just triggers a
     * re-render, which re-fetches messages fresh from the database rather
     * than trusting a client-supplied payload.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['id' => $this->message->id];
    }
}
