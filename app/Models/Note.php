<?php

namespace App\Models;

use Database\Factories\NoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\HtmlString;
use Laravel\Scout\Attributes\SearchUsingFullText;
use Laravel\Scout\Builder as ScoutBuilder;
use Laravel\Scout\Searchable;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\Mention\Mention;
use League\CommonMark\Extension\Mention\MentionExtension;
use League\CommonMark\MarkdownConverter;

#[Fillable(['title', 'body', 'user_id'])]
class Note extends Model
{
    /** @use HasFactory<NoteFactory> */
    use HasFactory, Searchable, SoftDeletes;

    /**
     * @return array<string, string>
     */
    #[SearchUsingFullText(['title', 'body'])]
    public function toSearchableArray(): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class);
    }

    protected function renderedBody(): Attribute
    {
        return Attribute::get(fn (): HtmlString => new HtmlString($this->markdownConverter()->convert($this->body)->getContent()));
    }

    /**
     * Search notes as the given user sees them: their subscribed teams' notes
     * plus teamless whole-pot notes, or everything when broader (or when the
     * user subscribes to no teams). Eager loads live here because Scout's
     * Builder::query() replaces its callback - callers must never chain their
     * own ->query() or they silently discard the team scoping.
     */
    public static function searchScoped(User $user, string $search, bool $broader = false): ScoutBuilder
    {
        $subscribedTeamIds = $user->subscribedTeams()->pluck('teams.id');

        if ($broader || $subscribedTeamIds->isEmpty()) {
            return static::search($search)->query(fn ($query) => $query->with(['user', 'teams']));
        }

        return static::search($search)->query(function ($query) use ($subscribedTeamIds) {
            $query->with(['user', 'teams'])->where(function ($query) use ($subscribedTeamIds) {
                $query->whereDoesntHave('teams')
                    ->orWhereHas('teams', fn ($teams) => $teams->whereIn('teams.id', $subscribedTeamIds));
            });
        });
    }

    /**
     * Attach the given teams, or the author's note defaults when none given.
     * Create paths only - on update, sync the explicit selection instead
     * (calling this with null would reset a note to the author's defaults).
     */
    public function assignTeams(?array $teamIds = null): void
    {
        $this->teams()->sync($teamIds ?? $this->user->defaultNoteTeams()->pluck('teams.id')->all());
    }

    private function markdownConverter(): MarkdownConverter
    {
        $environment = new Environment([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'mentions' => [
                'note' => [
                    'prefix' => '#',
                    'pattern' => '\d+',
                    'generator' => function (Mention $mention) {
                        $mention->setUrl(route('notes.show', $mention->getIdentifier()));

                        return $mention;
                    },
                ],
            ],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addExtension(new MentionExtension);

        return new MarkdownConverter($environment);
    }
}
