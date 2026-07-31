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
use Livewire\WithFileUploads;

class Composer extends Component
{
    use WithFileUploads;

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

    /**
     * A newly selected file pending upload - separate from the post's
     * already-attached media (see `existingMedia` in the view), since
     * a post's single `default` media slot is only replaced on save().
     */
    public $upload = null;

    /**
     * Base64 PNG data URL from the client-side Cropper.js step (see
     * resources/js/app.js's `imageCropper` Alpine component) - the plan's
     * reduced-scope substitute for a full Polotno embed. When set, this is
     * attached instead of the raw $upload file on save().
     */
    public ?string $croppedImage = null;

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
            'upload' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,mp4,mov', 'max:102400'],
        ];
    }

    public function save(CreatePost $createPost, UpdatePost $updatePost): void
    {
        $validated = $this->validate();
        $upload = $validated['upload'] ?? null;
        unset($validated['upload']);

        $post = $this->post
            ? $updatePost->execute($this->post, $validated)
            : $createPost->execute($validated);

        if ($this->croppedImage) {
            $post->addMediaFromBase64($this->croppedImage)
                ->usingName('cropped-image')
                ->toMediaCollection('default');
        } elseif ($upload) {
            $post->addMedia($upload->getRealPath())
                ->usingFileName($upload->getClientOriginalName())
                ->toMediaCollection('default');
        }

        $this->redirectRoute('launches.index');
    }

    public function removeMedia(): void
    {
        $this->post?->clearMediaCollection('default');
        $this->reset('croppedImage');
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
