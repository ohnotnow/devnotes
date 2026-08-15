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
