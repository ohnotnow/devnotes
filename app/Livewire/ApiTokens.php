<?php

namespace App\Livewire;

use Flux\Flux;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Attributes\Url;
use Livewire\Component;

class ApiTokens extends Component
{
    public string $tokenName = '';

    public string $newPlainTextToken = '';

    #[Url]
    public $showAll = false;

    public function render()
    {
        $showingAll = $this->isShowingAll();

        return view('livewire.api-tokens', [
            'showingAll' => $showingAll,
            'tokens' => $showingAll
                ? PersonalAccessToken::with('tokenable')->latest()->get()
                : auth()->user()->tokens()->latest()->get(),
        ]);
    }

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
        $tokens = auth()->user()->can('admin')
            ? PersonalAccessToken::query()
            : auth()->user()->tokens();

        $tokens->where('id', $tokenId)->delete();

        Flux::toast('Token revoked');
    }

    private function isShowingAll(): bool
    {
        return auth()->user()->can('admin') && filter_var($this->showAll, FILTER_VALIDATE_BOOL);
    }
}
