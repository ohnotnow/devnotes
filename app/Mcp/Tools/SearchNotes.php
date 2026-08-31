<?php

namespace App\Mcp\Tools;

use App\Models\Note;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Search the team\'s devnotes for gotchas, fixes, and lessons learned. Word search: every word must appear in the note\'s title or body, in any order, and partial words match (\'liber\' finds \'libero\'). Most recently updated first, capped at the 20 most recent - total_matches in the response gives the true match count, so if it exceeds the results returned, refine the query to reach older notes. Results are scoped to your subscribed channels by default; pass broader: true to search every channel\'s notes. Returns code, title, and a short snippet per match; use get-note with a code for the full note.')]
class SearchNotes extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'query' => ['required', 'string'],
            'broader' => ['sometimes', 'boolean'],
        ]);

        /** @var User $actingUser */
        $actingUser = $request->user();
        $user = User::findOrFail($actingUser->id);
        $broader = $validated['broader'] ?? false;
        $subscribedChannelIds = $user->subscribedChannels()->pluck('channels.id');

        $matches = Note::searchScoped($user, $validated['query'], $broader);
        $results = (clone $matches)->take(20)->get();

        $payload = [
            'total_matches' => $matches->count(),
            'results' => $results->map(function (Note $note) use ($broader, $subscribedChannelIds): array {
                $row = [
                    'code' => $note->code,
                    'title' => $note->title,
                    'snippet' => Str::limit($note->body, 200),
                    'channels' => $note->channels->pluck('name')->all(),
                ];

                if ($broader && $note->channels->isNotEmpty() && $note->channels->pluck('id')->intersect($subscribedChannelIds)->isEmpty() && $subscribedChannelIds->isNotEmpty()) {
                    $row['from_outside_your_channels'] = true;
                }

                return $row;
            })->all(),
        ];

        if (! $broader && $subscribedChannelIds->isNotEmpty()) {
            $payload['hint'] = 'Results were scoped to your subscribed channels. If nothing here answered the question, retry with broader: true.';
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
            'query' => $schema->string()
                ->description('Search terms matched against note titles and bodies.')
                ->required(),
            'broader' => $schema->boolean()
                ->description('Set true to search every channel\'s notes, not just your subscribed channels\'.'),
        ];
    }
}
