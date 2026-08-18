<?php

namespace App\Events;

use App\Enums\ActivityAction;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Events\Dispatchable;

class NoteActivityHappened
{
    use Dispatchable;

    /**
     * Carries plain values, not models - the listener is queued, and the
     * description must record the note as it was at the moment it happened.
     */
    public function __construct(
        public int $userId,
        public int $noteId,
        public ActivityAction $action,
        public string $description,
        public CarbonInterface $happenedAt,
    ) {}
}
