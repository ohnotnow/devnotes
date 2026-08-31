<?php

namespace App\Livewire\Admin;

use App\Models\Channel;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Channels extends Component
{
    public array $editing = [
        'id' => null,
        'name' => '',
    ];

    public ?Channel $deletingChannel = null;

    public function render()
    {
        return view('livewire.admin.channels', [
            'channels' => Channel::orderBy('name')->withCount(['subscribers', 'notes'])->get(),
        ]);
    }

    public function openCreate(): void
    {
        $this->reset('editing');
        $this->resetValidation();

        Flux::modal('channel-editor')->show();
    }

    public function openEdit(int $id): void
    {
        $this->editing = Channel::findOrFail($id)->only(['id', 'name']);
        $this->resetValidation();

        Flux::modal('channel-editor')->show();
    }

    public function save(): void
    {
        $this->validate([
            'editing.name' => ['required', 'string', 'max:255', Rule::unique('channels', 'name')->ignore($this->editing['id'])],
        ]);

        $channel = Channel::findOrNew($this->editing['id']);
        $channel->name = $this->editing['name'];
        $channel->save();

        Flux::modal('channel-editor')->close();
        Flux::toast("Saved channel {$channel->name}", variant: 'success');
    }

    public function openDelete(int $id): void
    {
        $this->deletingChannel = Channel::findOrFail($id);

        Flux::modal('channel-delete')->show();
    }

    public function deleteChannel(): void
    {
        if ($this->deletingChannel === null) {
            return;
        }

        $name = $this->deletingChannel->name;
        $this->deletingChannel->delete();

        $this->reset('deletingChannel');
        Flux::modal('channel-delete')->close();
        Flux::toast("Deleted channel {$name} - its notes now show in every search");
    }
}
