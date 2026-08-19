<?php

namespace App\Http\Resources;

use App\Models\Activity;
use App\Models\Note;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Note */
class ExportNoteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Part of the versioned export contract pinned by tests/fixtures/export-v1.json -
     * deliberately separate from NoteResource so the API can evolve without
     * breaking import compatibility.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'code' => $this->code,
            'title' => $this->title,
            'body' => $this->body,
            'author' => $this->userBlock($this->user),
            'teams' => $this->teams->pluck('name')->sort()->values()->all(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            'read_count' => $this->read_count,
            'last_read_at' => $this->last_read_at,
            'activities' => $this->activities->sortBy('id')->values()->map(fn (Activity $activity): array => [
                'ulid' => $activity->ulid,
                'actor' => $this->userBlock($activity->user),
                'action' => $activity->action->value,
                'description' => $activity->description,
                'created_at' => $activity->created_at,
            ])->all(),
        ];
    }

    /**
     * A user as the export records them - enough for an import to match by
     * email or recreate them. Null when the user has since been deleted.
     *
     * @return array<string, mixed>|null
     */
    private function userBlock(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'email' => $user->email,
            'forenames' => $user->forenames,
            'surname' => $user->surname,
            'is_admin' => $user->is_admin,
            'is_staff' => $user->is_staff,
        ];
    }
}
