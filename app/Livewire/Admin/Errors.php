<?php

namespace App\Livewire\Admin;

use App\Domain\Posts\Models\PostError;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Platform-wide, not organization-scoped - gated by the 'access-admin-panel'
 * Gate (routes/web.php), not BelongsToOrganization. PostError itself has no
 * tenancy scope of its own (inherits transitively through Post per
 * CLAUDE.md), but the eager-loaded `post` relation does, so it's bypassed
 * explicitly below the same way DispatchDuePosts/PublishPostJob do for
 * background work with no "current organization".
 */
class Errors extends Component
{
    use WithPagination;

    public string $type = '';

    public function render()
    {
        return view('livewire.admin.errors', [
            'errors' => PostError::query()
                ->when($this->type, fn ($query) => $query->where('type', $this->type))
                ->with(['post' => fn ($query) => $query->withoutGlobalScope('organization')->with('organization', 'integration')])
                ->latest()
                ->paginate(20),
        ]);
    }
}
