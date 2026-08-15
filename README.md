# devnotes

A CLI and MCP for capturing notes during a live coding session.

Somewhere between a wiki and a gist collection. When someone spends an afternoon on a weird Livewire quirk or a database driver difference, the fix gets captured as a quick note and is instantly available to anyone else who is a user of the back-end devnotes app. Notes are plain markdown, everyone can edit everything, nothing is precious. You can also shortcode `#id` to reference related notes.

There are three interfaces: a web UI for people, a JSON API for scripts and CLI tools, and an MCP server for coding agents.

## Prerequisites

- [Lando](https://lando.dev/) for local development (which brings its own PHP, database and node)
- A [Flux UI](https://fluxui.dev/) Pro licence. The interface is built with Flux, so `composer install` needs your Flux credentials.

## Getting started (local)

```sh
git clone https://github.com/ohnotnow/devnotes.git
cd devnotes
cp .env.example .env
lando start
lando composer config http-basic.composer.fluxui.dev your-flux-email your-flux-licence-key
lando composer install
lando npm install && lando npm run build
lando mfs   # migrate:fresh + seed test data
```

The seeder creates a local admin login of **admin2x** / **secret**.

Day to day, the usual suspects are `lando artisan`, `lando composer`, `lando npm`, and `lando mfs` whenever you want a fresh database.

## Production 

The codebase should be fairly straightforward to get running on the base tier of [Laravel Cloud](https://laravel.com/cloud).  If you want to host it elsewhere have a look through the [Laravel docs](https://laravel.com/docs/13.x/deployment).

## The API

Everything under `/api/v1` uses [Laravel Sanctum](https://laravel.com/docs/sanctum) bearer tokens. Users create their own tokens on the "API tokens" page in the web UI. 

```sh
curl -H "Authorization: Bearer $TOKEN" https://your-devnotes-host/api/v1/notes?search=livewire
```

The usual REST verbs work: list and search notes, fetch one, create, update (send the full payload, not a partial), and delete (soft, so `#id` references keep resolving).

## The MCP server

The MCP endpoint lives at `/mcp` and authenticates with OAuth 2.1. The client discovers the OAuth endpoints, registers itself, and pops a browser where you approve it while signed in to devnotes.

```sh
claude mcp add --transport http devnotes https://your-devnotes-host/mcp
```

Agents get three tools: `search-notes` (id, title and a short snippet per hit, scoped to your teams with a `broader: true` escape hatch), `get-note` (the full markdown, accepts `49` or `#49`), and `add-note` (returns the new note's id, tagging the note with your default teams or the team names you pass). The server's instructions nudge agents to search before debugging from scratch and to suggest capturing a note when a session solves something gnarly.

The OAuth keys come from `php artisan passport:keys` (run it once per environment). While you are developing, `config/mcp.php` allows any redirect domain; lock that down before putting the app anywhere public.

## Search

Search is [Laravel Scout](https://laravel.com/docs/scout). The example env is set up for meilisearch, but the `database` driver works fine for a small install with no extra moving parts, just set `SCOUT_DRIVER=database`.

On MySQL, MariaDB, and Postgres the `database` driver uses a full-text index on titles and bodies, so a multi-word search matches on words in any order, best matches first - no need to guess an exact phrase. Quirks worth knowing: MySQL skips words shorter than three characters and common stopwords; Postgres requires every word to appear and stems them using its english language config.

## Teams

Still one pot, but with tuned recall for mixed departments: notes and people can carry teams, and search shows your teams' notes plus any note with no team at all. Nothing is ever hidden - browsing, note pages, and `#id` references ignore teams entirely, and anyone can still read and edit everything. When a scoped search misses, every surface has a broader switch: a toggle next to the web search box, `broader: true` on the MCP tool, `?broader=1` on the API. New notes default to their author's teams, overridable per note. You tune your own subscriptions at `/settings/teams`; admins manage teams and memberships at `/admin/teams`. If you never create a team, nothing changes.

## Running tests

```sh
php artisan test --compact
```

## Contributing

Fork it, clone it, follow the getting started steps above, and check the tests pass before and after your change. Small, focused pull requests are very welcome. If you spot a gotcha worth sharing, well, that is rather the point of the whole thing.

## Licence

[MIT](LICENSE)
