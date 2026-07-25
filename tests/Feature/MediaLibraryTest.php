<?php

namespace Tests\Feature;

use App\Livewire\Media\Library;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // MediaLibrary writes to the real disk otherwise, polluting local dev storage.
        Storage::fake(config('media-library.disk_name'));
    }

    public function test_a_file_can_be_uploaded_to_the_organizations_media_library(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $organization = $user->currentTeam;

        Livewire::actingAs($user)->test(Library::class)
            ->set('upload', UploadedFile::fake()->image('photo.jpg'))
            ->call('save');

        $organization->refresh();

        $this->assertSame(1, $organization->getMedia('library')->count());
        $this->assertSame('photo.jpg', $organization->getMedia('library')->first()->file_name);
    }

    public function test_a_media_item_can_be_deleted(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $organization = $user->currentTeam;

        $organization->addMedia(UploadedFile::fake()->image('photo.jpg'))
            ->toMediaCollection('library');

        $media = $organization->getMedia('library')->first();

        Livewire::actingAs($user)->test(Library::class)
            ->call('delete', $media->id);

        $this->assertSame(0, $organization->fresh()->getMedia('library')->count());
    }
}
