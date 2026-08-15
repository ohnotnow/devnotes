<?php

namespace App\Models;

use App\Http\Resources\ExportNoteResource;
use Database\Factories\NoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Laravel\Scout\Attributes\SearchUsingFullText;
use Laravel\Scout\Builder as ScoutBuilder;
use Laravel\Scout\Searchable;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\Mention\Mention;
use League\CommonMark\Extension\Mention\MentionExtension;
use League\CommonMark\MarkdownConverter;
use RuntimeException;

#[Fillable(['title', 'body', 'user_id', 'code', 'ulid'])]
class Note extends Model
{
    /** @use HasFactory<NoteFactory> */
    use HasFactory, Searchable, SoftDeletes;

    /**
     * Encoding for export downloads, matching tests/fixtures/export-v1.json
     * byte-for-byte so a download IS the contract document.
     */
    public const EXPORT_JSON_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    /**
     * Lookalike-free (no l/o/i/0/1), always lowercase. The code is the note's
     * human reference (#abq4x) on every install it is ever imported into.
     */
    public const CODE_ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    public const CODE_LENGTH = 5;

    protected static function booted(): void
    {
        static::creating(function (Note $note) {
            if (blank($note->ulid)) {
                $note->ulid = (string) Str::ulid();
            }

            if (blank($note->code)) {
                $note->code = static::mintCode();
            }
        });
    }

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

    /**
     * The whole pot as a versioned export document. The shape is a contract
     * with imports, pinned by tests/fixtures/export-v1.json.
     *
     * @return array<string, mixed>
     */
    public static function exportPayload(): array
    {
        return [
            'version' => 1,
            'notes' => ExportNoteResource::collection(
                static::withTrashed()->with(['user', 'teams'])->orderBy('id')->get()
            )->resolve(),
        ];
    }

    /**
     * A deleted note keeps its code forever (withTrashed), so old #references
     * can never silently point at the wrong note.
     */
    public static function mintCode(): string
    {
        $attempts = 0;

        do {
            if (++$attempts > 50) {
                throw new RuntimeException('Could not mint a unique note code after 50 attempts - the code space looks saturated');
            }

            $code = '';
            for ($i = 0; $i < static::CODE_LENGTH; $i++) {
                $code .= static::CODE_ALPHABET[random_int(0, strlen(static::CODE_ALPHABET) - 1)];
            }
        } while (static::withTrashed()->where('code', $code)->exists());

        return $code;
    }

    private function markdownConverter(): MarkdownConverter
    {
        $environment = new Environment([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'mentions' => [
                'note' => [
                    'prefix' => '#',
                    // (?-i: because the commonmark inline parser matches case-insensitively by default
                    'pattern' => '(?-i:['.self::CODE_ALPHABET.']{'.self::CODE_LENGTH.'}(?![a-zA-Z0-9]))',
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
