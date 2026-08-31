<?php

namespace App\Livewire;

use App\Models\Channel;
use Flux\Flux;
use Livewire\Component;

class ChannelSettings extends Component
{
    public array $subscribedChannelIds = [];

    public array $defaultChannelIds = [];

    public function mount(): void
    {
        $this->subscribedChannelIds = auth()->user()->subscribedChannels()->pluck('channels.id')->all();
        $this->defaultChannelIds = auth()->user()->defaultNoteChannels()->pluck('channels.id')->all();
    }

    public function render()
    {
        return view('livewire.channel-settings', [
            'channels' => Channel::orderBy('name')->get(),
        ]);
    }

    public function save(): void
    {
        $this->validate([
            'subscribedChannelIds' => ['array'],
            'subscribedChannelIds.*' => ['exists:channels,id'],
            'defaultChannelIds' => ['array'],
            'defaultChannelIds.*' => ['exists:channels,id'],
        ]);

        auth()->user()->syncChannelPreferences(
            array_map('intval', $this->subscribedChannelIds),
            array_map('intval', $this->defaultChannelIds),
        );

        Flux::toast('Channels saved', variant: 'success');
    }
}
