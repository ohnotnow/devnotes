<?php

namespace App\Livewire;

use Flux\Flux;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

class ChangePassword extends Component
{
    public string $currentPassword = '';

    public string $newPassword = '';

    public string $newPassword_confirmation = '';

    public function mount(): void
    {
        abort_if(config('sso.enabled'), 404);
    }

    public function render()
    {
        return view('livewire.change-password');
    }

    public function save(): void
    {
        $this->validate([
            'currentPassword' => ['required', 'current_password'],
            'newPassword' => ['required', 'confirmed', Password::default()],
        ]);

        auth()->user()->update([
            'password' => bcrypt($this->newPassword),
            'must_change_password' => false,
        ]);

        $this->reset('currentPassword', 'newPassword', 'newPassword_confirmation');
        Flux::toast('Password changed', variant: 'success');
    }
}
