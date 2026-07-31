<?php

namespace App\Livewire\Agents;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Threads extends Component
{
    public function create(): void
    {
        $thread = Auth::user()->currentTeam->agentThreads()->create([]);

        $this->redirectRoute('agents.show', $thread);
    }

    public function render()
    {
        return view('livewire.agents.threads', [
            'threads' => Auth::user()->currentTeam->agentThreads()->latest()->get(),
        ]);
    }
}
