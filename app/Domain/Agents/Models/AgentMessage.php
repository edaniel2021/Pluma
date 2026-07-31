<?php

namespace App\Domain\Agents\Models;

use App\Domain\Agents\Enums\AgentMessageRole;
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

    public function thread(): BelongsTo
    {
        return $this->belongsTo(AgentThread::class, 'agent_thread_id');
    }
}
