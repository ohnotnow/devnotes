<?php

namespace App\Models;

use App\Enums\ActivityAction;
use Closure;
use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['ulid', 'user_id', 'note_id', 'action', 'description', 'created_at'])]
class Activity extends Model
{
    /** @use HasFactory<ActivityFactory> */
    use HasFactory;

    private static bool $loggingPaused = false;

    protected function casts(): array
    {
        return [
            'action' => ActivityAction::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Activity $activity) {
            if (blank($activity->ulid)) {
                $activity->ulid = (string) Str::ulid();
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Note, $this> */
    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class);
    }

    /**
     * Match the admin-page search box against the activity message (which
     * includes the #code and title) and the actor's name.
     */
    public function scopeMatching(Builder $query, string $search): void
    {
        $query->where(function (Builder $query) use ($search) {
            $query->whereLike('description', "%{$search}%")
                ->orWhereHas('user', function (Builder $user) use ($search) {
                    $user->whereLike('forenames', "%{$search}%")
                        ->orWhereLike('surname', "%{$search}%");
                });
        });
    }

    /**
     * Run the callback with activity logging switched off - for bulk paths
     * like import, where logging every row would write misleading history.
     * try/finally (user-approved): no catch, exceptions still reach Sentry;
     * finally guarantees a failure mid-callback cannot leave logging paused
     * for later jobs in a long-lived queue worker process.
     */
    public static function withoutLogging(Closure $callback): mixed
    {
        self::$loggingPaused = true;

        try {
            return $callback();
        } finally {
            self::$loggingPaused = false;
        }
    }

    public static function loggingIsPaused(): bool
    {
        return self::$loggingPaused;
    }
}
