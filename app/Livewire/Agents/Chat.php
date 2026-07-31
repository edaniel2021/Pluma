<?php

namespace App\Livewire\Agents;

use App\Domain\Agents\Actions\SendAgentMessage;
use App\Domain\Agents\Enums\AgentMessageRole;
use App\Domain\Agents\Models\AgentThread;
use Livewire\Attributes\On;
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
     * Real-time upgrade over the wire:poll fallback below: Reverb pushes
     * this the moment AgentMessage::booted() fires AgentMessageCreated for
     * this thread. The handler body is deliberately empty - re-rendering
     * with a freshly rehydrated $thread (Livewire re-fetches public model
     * properties from the database each request) is all that's needed,
     * rather than trusting the event's payload.
     *
     * The leading `.` on the event name is required: without it, Echo
     * assumes the event class lives under `App\Events\*` (the historical
     * Laravel default) and never matches AgentMessageCreated::broadcastAs(),
     * so the listener silently never fires.
     */
    #[On('echo-private:agent-thread.{thread.id},.AgentMessageCreated')]
    public function onAgentMessageCreated(): void
    {
        //
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
