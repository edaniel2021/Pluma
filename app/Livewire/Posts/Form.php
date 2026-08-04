<?php

namespace App\Livewire\Posts;

use App\Domain\Posts\Actions\CreatePost;
use App\Domain\Posts\Actions\UpdatePost;
use App\Domain\Posts\Enums\PostState;
use App\Domain\Posts\Models\Post;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Form extends Component
{
    public ?Post $post = null;

    public string $content = '';

    public string $state = 'draft';

    public ?string $scheduled_at = null;

    public function mount(?Post $post = null): void
    {
        if ($post?->exists) {
            $this->post = $post;
            $this->content = $post->content;
            $this->state = $post->state->value;
            $this->scheduled_at = $post->scheduled_at?->format('Y-m-d\TH:i');
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'content' => ['required', 'string'],
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

        $this->redirectRoute('posts.index');
    }

    /**
     * @return array<int, PostState>
     */
    public function getStatesProperty(): array
    {
        return PostState::cases();
    }

    /**
     * Read-only preview only - this plain CRUD form (unlike the
     * integration-aware Launches Composer) has no upload/crop UI of its
     * own. Posts with an attached image (e.g. scheduled via the AI
     * assistant, or via the Composer) previously showed nothing at all
     * here, silently - a real complaint ("I don't see the image").
     */
    public function getExistingMediaProperty(): ?Media
    {
        return $this->post?->getFirstMedia('default');
    }

    public function render()
    {
        return view('livewire.posts.form');
    }
}
