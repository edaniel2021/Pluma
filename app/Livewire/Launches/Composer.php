<?php

namespace App\Livewire\Launches;

use App\Domain\Posts\Actions\CreatePost;
use App\Domain\Posts\Actions\DeletePost;
use App\Domain\Posts\Actions\UpdatePost;
use App\Domain\Posts\Enums\PostState;
use App\Domain\Posts\Models\Post;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

class Composer extends Component
{
    /**
     * Per-platform character limits - the plan's "TipTap character-limit
     * rules" carried over as a plain lookup rather than editor config,
     * since the composer edits plain text, not per-platform rich content.
     *
     * @var array<string, int>
     */
    protected const CHARACTER_LIMITS = [
        'x' => 280,
        'linkedin' => 3000,
    ];

    public ?Post $post = null;

    public string $content = '';

    public ?int $integration_id = null;

    public string $state = 'queue';

    public ?string $scheduled_at = null;

    #[Url]
    public ?string $date = null;

    public function mount(?Post $post = null): void
    {
        if ($post?->exists) {
            $this->post = $post;
            $this->content = $post->content;
            $this->integration_id = $post->integration_id;
            $this->state = $post->state->value;
            $this->scheduled_at = $post->scheduled_at?->format('Y-m-d\TH:i');

            return;
        }

        if ($this->date) {
            $this->scheduled_at = $this->date.'T09:00';
        }

        $this->integration_id = Auth::user()->currentTeam->integrations()->value('id');
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'content' => ['required', 'string'],
            'integration_id' => [
                'required',
                Rule::exists('integrations', 'id')->where('organization_id', Auth::user()->currentTeam->id),
            ],
            'state' => ['required', Rule::enum(PostState::class)],
            'scheduled_at' => ['nullable', 'date'],
        ];
    }

    public function save(CreatePost $createPost, UpdatePost $updatePost): void
    {
        $validated = $this->validate();

        if ($this->post) {
            $updatePost->execute($this->post, $validated);
        } else {
            $createPost->execute($validated);
        }

        $this->redirectRoute('launches.index');
    }

    public function delete(DeletePost $deletePost): void
    {
        if ($this->post) {
            $deletePost->execute($this->post);
        }

        $this->redirectRoute('launches.index');
    }

    /**
     * @return array<int, PostState>
     */
    public function getStatesProperty(): array
    {
        return PostState::cases();
    }

    public function getCharacterLimitProperty(): ?int
    {
        $provider = Auth::user()->currentTeam->integrations()->find($this->integration_id)?->provider;

        return self::CHARACTER_LIMITS[$provider] ?? null;
    }

    public function render()
    {
        return view('livewire.launches.composer', [
            'integrations' => Auth::user()->currentTeam->integrations,
        ]);
    }
}
