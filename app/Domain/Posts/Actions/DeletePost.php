<?php

namespace App\Domain\Posts\Actions;

use App\Domain\Posts\Models\Post;

class DeletePost
{
    public function execute(Post $post): void
    {
        $post->delete();
    }
}
