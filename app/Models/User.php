<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['username', 'email', 'password', 'surname', 'forenames', 'is_admin', 'is_staff'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)->withPivot(['subscribed', 'note_default']);
    }

    public function subscribedTeams(): BelongsToMany
    {
        return $this->teams()->wherePivot('subscribed', true);
    }

    public function defaultNoteTeams(): BelongsToMany
    {
        return $this->teams()->wherePivot('note_default', true);
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => trim($this->forenames.' '.$this->surname));
    }
}
