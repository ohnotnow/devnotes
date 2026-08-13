# devnotes

A shared pot of dev-team gotchas, kept as tiny markdown notes you can actually find again.

Somewhere between a wiki and a gist collection. When someone on the team burns an afternoon on a weird Livewire quirk or a database driver difference, the fix gets captured as a small note. Notes are plain markdown, everyone can edit everything, nothing is precious. Notes can reference each other with `#id`, which renders as a link.

There are three ways in, all backed by the same data: a web UI for people, a JSON API for scripts and CLI tools, and an MCP server so coding agents can search and capture notes mid-session.

## Prerequisites

- [Lando](https://lando.dev/) for local development (which brings its own PHP, database and node)
- A [Flux UI](https://fluxui.dev/) Pro licence. The interface is built with Flux, so `composer install` needs your Flux credentials.

## Getting started

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

Sign-in is normally handled by SSO (Keycloak via Socialite), but with `SSO_ENABLED=false` in your `.env` you get a plain local login form instead, which is what you want for development.

## The API

Everything under `/api/v1` uses [Laravel Sanctum](https://laravel.com/docs/sanctum) bearer tokens. Users create their own tokens on the "API tokens" page in the web UI. Responses are all `data`-wrapped JSON.

```sh
curl -H "Authorization: Bearer $TOKEN" https://your-devnotes-host/api/v1/notes?search=livewire
```

The usual REST verbs work: list and search notes, fetch one, create, update (send the full payload, not a partial), and delete (soft, so `#id` references keep resolving).

## The MCP server

The MCP endpoint lives at `/mcp` and authenticates with OAuth 2.1. The client discovers the OAuth endpoints, registers itself, and pops a browser where you approve it while signed in to devnotes.

```sh
claude mcp add --transport http devnotes https://your-devnotes-host/mcp
```

Agents get three tools: `search-notes` (id, title and a short snippet per hit), `get-note` (the full markdown, accepts `49` or `#49`), and `add-note` (returns the new note's id). The server's instructions nudge agents to search before debugging from scratch and to suggest capturing a note when a session solves something gnarly.

One operational note: MCP clients cache the tool list when they connect, so after deploying new or changed tools your client needs a reconnect before it sees them.

The OAuth keys come from `php artisan passport:keys` (run it once per environment). While you are developing, `config/mcp.php` allows any redirect domain; lock that down before putting the app anywhere public.

## Search

Search is [Laravel Scout](https://laravel.com/docs/scout). The example env is set up for meilisearch, but the `database` driver works fine for a small install with no extra moving parts, just set `SCOUT_DRIVER=database`.

## Running tests

```sh
php artisan test --compact
```

The suite runs against an in-memory sqlite database, so there is nothing to set up first.

## Contributing

Fork it, clone it, follow the getting started steps above, and check the tests pass before and after your change. Small, focused pull requests are very welcome. If you spot a gotcha worth sharing, well, that is rather the point of the whole thing.

## Licence

[MIT](LICENSE)
