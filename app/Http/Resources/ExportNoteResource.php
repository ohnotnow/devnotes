<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'author' => [
                'email' => $this->user->email,
                'forenames' => $this->user->forenames,
                'surname' => $this->user->surname,
                'is_admin' => $this->user->is_admin,
                'is_staff' => $this->user->is_staff,
            ],
            'teams' => $this->teams->pluck('name')->sort()->values()->all(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
