<?php

namespace App\Livewire;

use Flux\Flux;
use Livewire\Component;

class ApiTokens extends Component
{
    public string $tokenName = '';

    public string $newPlainTextToken = '';

    public function create(): void
    {
        $this->validate([
            'tokenName' => ['required', 'string', 'max:255'],
        ]);

        $this->newPlainTextToken = auth()->user()->createToken($this->tokenName)->plainTextToken;

        $this->reset('tokenName');
        Flux::modal('token-create')->close();
    }

    public function revoke(int $tokenId): void
    {
        auth()->user()->tokens()->where('id', $tokenId)->delete();

        Flux::toast('Token revoked');
    }

    public function render()
    {
        return view('livewire.api-tokens', [
            'tokens' => auth()->user()->tokens()->latest()->get(),
        ]);
    }
}
