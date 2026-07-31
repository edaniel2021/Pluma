<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Posts\Actions\CreatePost;
use App\Domain\Posts\Actions\DeletePost;
use App\Domain\Posts\Actions\UpdatePost;
use App\Domain\Posts\Enums\PostState;
use App\Domain\Posts\Models\Post;
use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PostController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return PostResource::collection(
            Post::query()->latest()->paginate(min($request->integer('per_page', 15), 100))
        );
    }

    public function store(Request $request, CreatePost $createPost): PostResource
    {
        $validated = $request->validate($this->rules());

        return new PostResource($createPost->execute($validated));
    }

    public function show(Post $post): PostResource
    {
        return new PostResource($post);
    }

    public function update(Request $request, Post $post, UpdatePost $updatePost): PostResource
    {
        $validated = $request->validate($this->rules(partial: true));

        return new PostResource($updatePost->execute($post, $validated));
    }

    public function destroy(Post $post, DeletePost $deletePost): Response
    {
        $deletePost->execute($post);

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'content' => [$required, 'string'],
            'integration_id' => [
                'nullable',
                Rule::exists('integrations', 'id')->where('organization_id', Auth::user()->currentTeam->id),
            ],
            'state' => ['sometimes', Rule::enum(PostState::class)],
            'scheduled_at' => ['nullable', 'date'],
        ];
    }
}
