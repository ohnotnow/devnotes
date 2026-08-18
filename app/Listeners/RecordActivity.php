<?php

namespace App\Listeners;

use App\Events\NoteActivityHappened;
use App\Models\Activity;
use Illuminate\Contracts\Queue\ShouldQueue;

class RecordActivity implements ShouldQueue
{
    public function handle(NoteActivityHappened $event): void
    {
        Activity::create([
            'user_id' => $event->userId,
            'note_id' => $event->noteId,
            'action' => $event->action,
            'description' => $event->description,
            'created_at' => $event->happenedAt,
        ]);
    }
}
