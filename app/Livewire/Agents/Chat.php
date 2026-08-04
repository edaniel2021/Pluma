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
     * Drives whether the view keeps polling (the Reverb fallback - see the
     * onAgentMessageCreated() docblock). A turn can span several tool-call
     * rounds before the model produces its final plain-text reply, and
     * ProcessAgentMessageJob persists an AgentMessage after every single
     * step (each tool_calls request, each tool result) - not just at the
     * very end. Treating "last message isn't the user's" as "done" made
     * polling stop after the *first* such step, well before the turn
     * actually finished: every message after that point depended entirely
     * on its Reverb broadcast arriving, with no fallback if one was ever
     * dropped (a real symptom - "sometimes I have to refresh to see the
     * answer"). The turn is only truly over once the last message is a
     * plain-text Assistant reply with no pending tool_calls (or the job
     * gave up and posted a failure message, which looks the same).
     */
    public function getIsWaitingProperty(): bool
    {
        $last = $this->thread->messages->last();

        if (! $last) {
            return false;
        }

        return $last->role === AgentMessageRole::User
            || $last->role === AgentMessageRole::Tool
            || ($last->role === AgentMessageRole::Assistant && ! empty($last->tool_calls));
    }

    public function render()
    {
        return view('livewire.agents.chat', [
            'messages' => $this->thread->messages,
        ]);
    }
}
