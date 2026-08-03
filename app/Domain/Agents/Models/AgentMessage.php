<?php

namespace App\Domain\Agents\Models;

use App\Domain\Agents\Enums\AgentMessageRole;
use App\Domain\Agents\Events\AgentMessageCreated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentMessage extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'role',
        'content',
        'tool_name',
        'tool_call_id',
        'tool_calls',
    ];

    protected function casts(): array
    {
        return [
            'role' => AgentMessageRole::class,
            'tool_calls' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (AgentMessage $message) {
            // ShouldBroadcastNow means this happens synchronously, inline
            // with whatever created the message (a web request or
            // ProcessAgentMessageJob) - a Reverb outage/misconfiguration
            // must not be able to take that down too. Caught broadly
            // (not just BroadcastException) since a config problem can
            // throw before the broadcast attempt even starts.
            try {
                AgentMessageCreated::dispatch($message);
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(AgentThread::class, 'agent_thread_id');
    }
}
