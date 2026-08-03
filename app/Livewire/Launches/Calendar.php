<?php

namespace App\Livewire\Launches;

use App\Domain\Posts\Enums\PostState;
use App\Domain\Posts\Models\Post;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Calendar extends Component
{
    /**
     * Called from FullCalendar's eventDrop handler when a launch is
     * dragged to a new date/time. Returns false (rather than throwing) on
     * anything that shouldn't move, so the JS side can revert the drag.
     */
    public function reschedule(int $postId, string $newDateTime): bool
    {
        $post = Post::whereIn('state', [PostState::Draft, PostState::Queue])->find($postId);

        if (! $post) {
            return false;
        }

        // FullCalendar is configured (see resources/js/app.js) to render in
        // the org's timezone via its `timeZone` option, so the string it
        // hands back here is a naive wall-clock time in that same zone, not
        // UTC - same conversion as the Composer's scheduled_at input.
        $post->update([
            'scheduled_at' => Carbon::parse($newDateTime, Auth::user()->currentTeam->timezone)->setTimezone('UTC'),
        ]);

        return true;
    }

    public function render()
    {
        $posts = Post::whereNotNull('integration_id')
            ->whereNotNull('scheduled_at')
            ->with('integration')
            ->get();

        $events = $posts->map(fn (Post $post) => [
            'id' => $post->id,
            'title' => (string) str($post->content)->limit(40),
            'start' => $post->scheduled_at->toIso8601String(),
            'color' => match ($post->state) {
                PostState::Published => '#16a34a',
                PostState::Error => '#dc2626',
                PostState::Queue => '#2563eb',
                PostState::Draft => '#9ca3af',
            },
            'extendedProps' => [
                'postId' => $post->id,
                'state' => $post->state->value,
            ],
        ])->values();

        return view('livewire.launches.calendar', [
            'events' => $events,
            'timezone' => Auth::user()->currentTeam->timezone,
        ]);
    }
}
