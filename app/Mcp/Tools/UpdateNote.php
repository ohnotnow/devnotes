<?php

namespace App\Mcp\Tools;

use App\Models\Note;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Arr;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Update an existing devnote\'s title and/or body. Reach for this when a finding evolves mid-session (the fix turned out to be different, the cause got clearer), or to merge a near-duplicate that add-note flagged, instead of leaving two notes covering the same ground. Accepts the note code in either bare (abq4x) or hash-prefixed (#abq4x) form.')]
class UpdateNote extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
            'title' => ['required_without:body', 'string', 'max:255'],
            'body' => ['required_without:title', 'string'],
        ]);

        $note = Note::where('code', ltrim($validated['code'], '#'))->first();

        if (! $note) {
            return Response::error("No note found with code {$validated['code']}. Use search-notes to find the right code.");
        }

        $note->update(Arr::only($validated, ['title', 'body']));

        return Response::json([
            'code' => $note->code,
            'title' => $note->title,
        ]);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'code' => $schema->string()
                ->description('The note code, with or without the leading #.')
                ->required(),
            'title' => $schema->string()
                ->description('A new title for the note. Optional, but at least one of title or body is required.'),
            'body' => $schema->string()
                ->description('The full replacement body as markdown - not a diff or an addition.'),
        ];
    }
}
