<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Str;
use Livewire\Component;

class Users extends Component
{
    public string $email = '';

    public ?User $deletingUser = null;

    public $transferToId = '';

    public function add(): void
    {
        $this->email = strtolower(trim($this->email));

        $this->validate([
            'email' => ['required', 'email', 'unique:users,email'],
        ]);

        User::create([
            'email' => $this->email,
            'username' => '',
            'surname' => '',
            'forenames' => '',
            'is_staff' => true,
            'password' => bcrypt(Str::random(64)),
        ]);

        $this->reset('email');
        Flux::modal('user-add')->close();
        Flux::toast('Added - their details will fill in when they first log in', variant: 'success');
    }

    public function toggleAdmin(int $userId): void
    {
        $user = User::findOrFail($userId);

        if ($user->is(auth()->user())) {
            Flux::toast('You cannot change your own admin status', variant: 'danger');

            return;
        }

        $user->update(['is_admin' => ! $user->is_admin]);

        Flux::toast($user->full_name !== '' ? "Changed admin status for {$user->full_name}" : 'Changed admin status');
    }

    public function openDelete(int $userId): void
    {
        $this->deletingUser = User::findOrFail($userId);
        $this->transferToId = (string) auth()->id();

        Flux::modal('user-delete')->show();
    }

    public function deleteUser(): void
    {
        if ($this->deletingUser === null || $this->deletingUser->is(auth()->user())) {
            Flux::toast('You cannot delete your own account', variant: 'danger');

            return;
        }

        $this->validate([
            'transferToId' => ['required', 'exists:users,id', 'not_in:'.$this->deletingUser->id],
        ]);

        $recipient = User::findOrFail($this->transferToId);

        $this->deletingUser->notes()->withTrashed()->update(['user_id' => $recipient->id]);
        $this->deletingUser->delete();

        $this->reset('deletingUser', 'transferToId');
        Flux::modal('user-delete')->close();
        Flux::toast("Deleted - their notes now belong to {$recipient->full_name}");
    }

    public function render()
    {
        return view('livewire.admin.users', [
            'users' => User::orderBy('surname')->orderBy('email')->withCount('notes')->get(),
        ]);
    }
}
