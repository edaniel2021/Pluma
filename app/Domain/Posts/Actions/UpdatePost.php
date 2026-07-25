<?php

namespace App\Domain\Posts\Actions;

use App\Domain\Posts\Models\Post;

class UpdatePost
{
    /**
     * @param  array{content: string, state?: string, scheduled_at?: ?string}  $input
     */
    public function execute(Post $post, array $input): Post
    {
        $post->update($input);

        return $post;
    }
}
