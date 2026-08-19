<?php

namespace App\Mcp\Tools;

use App\Models\Note;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Capture a new devnote: a small markdown note recording a gotcha, fix, or lesson learned. Optionally tag it with team names; untagged notes go to your default teams. Returns the new note code, which can be cited as #code in other notes and chat.')]
class AddNote extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'teams' => ['sometimes', 'array'],
            'teams.*' => ['string', 'exists:teams,name'],
        ]);

        /** @var User $actingUser */
        $actingUser = $request->user();

        $note = Note::create([
            'title' => $validated['title'],
            'body' => $validated['body'],
            'user_id' => $actingUser->id,
        ]);

        $note->assignTeams(isset($validated['teams'])
            ? Team::whereIn('name', $validated['teams'])->pluck('id')->all()
            : null);

        $payload = [
            'code' => $note->code,
            'title' => $note->title,
        ];

        $user = User::findOrFail($actingUser->id);
        $similarNotes = Note::similarTo($user, $note->title)
            ->reject(fn (Note $found) => $found->is($note))
            ->take(3);

        if ($similarNotes->isNotEmpty()) {
            $payload['similar_notes'] = $similarNotes->map(fn (Note $found) => [
                'code' => $found->code,
                'title' => $found->title,
            ])->values()->all();
            $payload['hint'] = 'These existing notes may cover the same ground. If one does, update-note can merge this finding into it instead.';
        }

        return Response::json($payload);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()
                ->description('A short, searchable title for the note.')
                ->required(),
            'body' => $schema->string()
                ->description('The note body as markdown. Write for a stranger: symptom, cause, fix, breadcrumbs.')
                ->required(),
            'teams' => $schema->array()
                ->items($schema->string())
                ->description('Team names to tag the note with. Omit for your default teams; pass [] for a note every team sees.'),
        ];
    }
}
