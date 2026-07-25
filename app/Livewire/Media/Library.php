<?php

namespace App\Livewire\Media;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

class Library extends Component
{
    use WithFileUploads;

    #[Validate('nullable|file|max:10240')]
    public $upload = null;

    public function save(): void
    {
        $this->validate();

        if ($this->upload) {
            Auth::user()->currentTeam
                ->addMedia($this->upload->getRealPath())
                ->usingFileName($this->upload->getClientOriginalName())
                ->toMediaCollection('library');

            $this->reset('upload');
        }
    }

    public function delete(int $mediaId): void
    {
        Auth::user()->currentTeam
            ->media()
            ->findOrFail($mediaId)
            ->delete();
    }

    public function render()
    {
        return view('livewire.media.library', [
            'media' => Auth::user()->currentTeam->getMedia('library'),
        ]);
    }
}
