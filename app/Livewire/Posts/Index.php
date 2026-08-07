<?php

namespace App\Livewire\Posts;

use App\Domain\Posts\Actions\DeletePost;
use App\Domain\Posts\Models\Post;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public function delete(Post $post, DeletePost $deletePost): void
    {
        $deletePost->execute($post);
    }

    public function render()
    {
        return view('livewire.posts.index', [
            // The BelongsToOrganization global scope already restricts this
            // to the active organization's posts. Eager-loads integration
            // so the platform icon per post doesn't N+1.
            'posts' => Post::with('integration')->latest()->paginate(10),
        ]);
    }
}
