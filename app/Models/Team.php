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

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot(['subscribed', 'note_default']);
    }

    /** @return BelongsToMany<Note, $this> */
    public function notes(): BelongsToMany
    {
        return $this->belongsToMany(Note::class);
    }

    /**
     * A visually distinct subset of the flux badge colours - near-identical
     * hues (sky/blue/cyan etc.) are left out so neighbouring badges stay
     * tellable-apart when skimming the notes list.
     *
     * The order is deliberately scrambled: teams tend to be created in one
     * sitting, so consecutive ids must land on distant hues. Do not re-sort
     * this list into colour-wheel or alphabetical order.
     */
    public function colour(): string
    {
        $palette = ['red', 'lime', 'sky', 'rose', 'amber', 'teal', 'fuchsia', 'orange', 'emerald', 'indigo'];

        return $palette[$this->id % count($palette)];
    }
}
