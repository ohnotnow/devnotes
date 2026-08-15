<?php

namespace App\Models;

use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name'])]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory;

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot(['subscribed', 'note_default']);
    }

    public function notes(): BelongsToMany
    {
        return $this->belongsToMany(Note::class);
    }
}
