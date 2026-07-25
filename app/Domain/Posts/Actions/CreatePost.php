<?php

namespace App\Domain\Posts\Actions;

use App\Domain\Posts\Models\Post;

class CreatePost
{
    /**
     * @param  array{content: string, state?: string, scheduled_at?: ?string}  $input
     */
    public function execute(array $input): Post
    {
        // organization_id and user_id are auto-filled by BelongsToOrganization
        // and Post::booted()'s creating hook respectively.
        return Post::create($input);
    }
}
