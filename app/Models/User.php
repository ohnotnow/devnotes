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

/**
 * @property-read string $full_name
 */
#[Fillable(['username', 'email', 'password', 'surname', 'forenames', 'is_admin', 'is_staff', 'must_change_password'])]
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
            'is_admin' => 'boolean',
            'is_staff' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    /** @return HasMany<Note, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    /** @return BelongsToMany<Channel, $this> */
    public function channels(): BelongsToMany
    {
        return $this->belongsToMany(Channel::class)->withPivot(['subscribed', 'note_default']);
    }

    /** @return BelongsToMany<Channel, $this> */
    public function subscribedChannels(): BelongsToMany
    {
        return $this->channels()->wherePivot('subscribed', true);
    }

    /** @return BelongsToMany<Channel, $this> */
    public function defaultNoteChannels(): BelongsToMany
    {
        return $this->channels()->wherePivot('note_default', true);
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => trim($this->forenames.' '.$this->surname));
    }

    /**
     * Replace the user's channel rows from two checkbox selections. A channel
     * in either list keeps a row with the matching flags; a channel in
     * neither loses its row entirely.
     *
     * @param  array<int>  $subscribedChannelIds
     * @param  array<int>  $noteDefaultChannelIds
     */
    public function syncChannelPreferences(array $subscribedChannelIds, array $noteDefaultChannelIds): void
    {
        $rows = collect($subscribedChannelIds)
            ->merge($noteDefaultChannelIds)
            ->unique()
            ->mapWithKeys(fn (int $channelId): array => [$channelId => [
                'subscribed' => in_array($channelId, $subscribedChannelIds),
                'note_default' => in_array($channelId, $noteDefaultChannelIds),
            ]]);

        $this->channels()->sync($rows->all());
    }
}
