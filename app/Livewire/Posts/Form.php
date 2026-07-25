<?php

namespace App\Livewire\Posts;

use App\Domain\Posts\Actions\CreatePost;
use App\Domain\Posts\Actions\UpdatePost;
use App\Domain\Posts\Enums\PostState;
use App\Domain\Posts\Models\Post;
use Illuminate\Validation\Rule;
use Livewire\Component;

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

    public function render()
    {
        return view('livewire.posts.form');
    }
}
