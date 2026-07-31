<?php

namespace App\Livewire\Agents;

use App\Domain\Agents\Actions\SendAgentMessage;
use App\Domain\Agents\Enums\AgentMessageRole;
use App\Domain\Agents\Models\AgentThread;
use Livewire\Component;

class Chat extends Component
{
    public AgentThread $thread;

    public string $content = '';

    public function mount(AgentThread $thread): void
    {
        $this->thread = $thread;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:4000'],
        ];
    }

    public function send(SendAgentMessage $sendAgentMessage): void
    {
        $validated = $this->validate();

        $sendAgentMessage->execute($this->thread, $validated['content']);

        $this->reset('content');
    }

    /**
     * Drives whether the view keeps polling: true while the last message is
     * still the user's (no assistant reply yet, meaning ProcessAgentMessageJob
     * hasn't finished/failed).
     */
    public function getIsWaitingProperty(): bool
    {
        return $this->thread->messages->last()?->role === AgentMessageRole::User;
    }

    public function render()
    {
        return view('livewire.agents.chat', [
            'messages' => $this->thread->messages,
        ]);
    }
}
