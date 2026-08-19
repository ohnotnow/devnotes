<?php

namespace App\Livewire\Admin;

use App\Models\Team;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Users extends Component
{
    public string $email = '';

    public string $username = '';

    public string $forenames = '';

    public string $surname = '';

    public string $newTempPassword = '';

    public array $selectedTeamIds = [];

    public ?User $editingUser = null;

    public ?User $deletingUser = null;

    public $transferToId = '';

    public function render()
    {
        return view('livewire.admin.users', [
            'users' => User::orderBy('surname')->orderBy('email')->withCount('notes')->with('teams')->get(),
            'teams' => Team::orderBy('name')->get(),
        ]);
    }

    public function openAdd(): void
    {
        $this->reset('editingUser', 'email', 'username', 'forenames', 'surname', 'selectedTeamIds');
        $this->resetValidation();

        Flux::modal('user-add')->show();
    }

    public function openEdit(int $userId): void
    {
        $this->editingUser = User::findOrFail($userId);
        $this->email = $this->editingUser->email;
        $this->username = $this->editingUser->username;
        $this->forenames = $this->editingUser->forenames;
        $this->surname = $this->editingUser->surname;
        $this->selectedTeamIds = $this->editingUser->teams()->pluck('teams.id')->all();
        $this->resetValidation();

        Flux::modal('user-add')->show();
    }

    public function save(): void
    {
        if ($this->editingUser === null) {
            $this->createNewUser();

            return;
        }

        $this->updateExistingUser();
    }

    private function updateExistingUser(): void
    {
        $user = $this->editingUser;

        $this->validate($this->editValidationRules($user));
        $this->applyIdentityDetails($user);

        $teamIds = array_map('intval', $this->selectedTeamIds);
        $user->syncTeamPreferences($teamIds, $teamIds);

        $this->reset('editingUser', 'email', 'username', 'forenames', 'surname', 'selectedTeamIds');
        Flux::modal('user-add')->close();
        Flux::toast('Saved', variant: 'success');
    }

    private function editValidationRules(User $user): array
    {
        $rules = [
            'selectedTeamIds' => ['array'],
            'selectedTeamIds.*' => ['exists:teams,id'],
        ];

        if (config('sso.enabled')) {
            return $rules;
        }

        $this->email = strtolower(trim($this->email));
        $this->username = strtolower(trim($this->username));

        $rules['email'] = ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)];
        $rules['username'] = ['required', Rule::unique('users', 'username')->ignore($user->id)];

        return $rules;
    }

    public function regeneratePassword(): void
    {
        if (config('sso.enabled') || $this->editingUser === null) {
            return;
        }

        $this->newTempPassword = Str::random(12);
        $this->editingUser->update([
            'password' => bcrypt($this->newTempPassword),
            'must_change_password' => true,
        ]);

        $this->reset('editingUser', 'email', 'username', 'forenames', 'surname', 'selectedTeamIds');
        Flux::modal('user-add')->close();
        Flux::toast('New temporary password - copy it to pass on', variant: 'success');
    }

    private function applyIdentityDetails(User $user): void
    {
        if (config('sso.enabled')) {
            return;
        }

        $user->update([
            'email' => $this->email,
            'username' => $this->username,
            'forenames' => trim($this->forenames),
            'surname' => trim($this->surname),
        ]);
    }

    private function createNewUser(): void
    {
        $this->email = strtolower(trim($this->email));
        $this->username = strtolower(trim($this->username));

        $rules = [
            'email' => ['required', 'email', 'unique:users,email'],
            'selectedTeamIds' => ['array'],
            'selectedTeamIds.*' => ['exists:teams,id'],
        ];
        if (! config('sso.enabled')) {
            $rules['username'] = ['required', 'unique:users,username'];
        }
        $this->validate($rules);

        $newUser = config('sso.enabled') ? $this->createSsoStub() : $this->createLocalUser();

        $teamIds = array_map('intval', $this->selectedTeamIds);
        $newUser->syncTeamPreferences($teamIds, $teamIds);

        $this->reset('email', 'username', 'forenames', 'surname', 'selectedTeamIds');
        Flux::modal('user-add')->close();
        Flux::toast(
            config('sso.enabled')
                ? 'Added - their details will fill in when they first log in'
                : 'Added - copy their temporary password to pass on',
            variant: 'success',
        );
    }

    private function createSsoStub(): User
    {
        return User::create([
            'email' => $this->email,
            'username' => '',
            'surname' => '',
            'forenames' => '',
            'is_staff' => true,
            'password' => bcrypt(Str::random(64)),
        ]);
    }

    private function createLocalUser(): User
    {
        $this->newTempPassword = Str::random(12);

        return User::create([
            'email' => $this->email,
            'username' => $this->username,
            'surname' => trim($this->surname),
            'forenames' => trim($this->forenames),
            'is_staff' => true,
            'password' => bcrypt($this->newTempPassword),
            'must_change_password' => true,
        ]);
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
}
