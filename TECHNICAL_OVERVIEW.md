# Technical overview

Last updated: 2026-08-15

## What this is

A shared pot of dev-team gotchas kept as tiny markdown notes, served three ways from one Laravel app: a web UI for people, a JSON API for scripts and CLI tools, and an MCP server for coding agents.

## Stack

- PHP 8.4 / Laravel 13
- Livewire 4 + Flux UI Pro 2 (licence required for `composer install`)
- Sanctum (API tokens), Passport 13 (MCP OAuth), laravel/mcp 0.9
- Scout for search (`database` or `meilisearch` driver)
- Socialite + Keycloak for SSO, with a local login fallback
- Pest 5, in-memory sqlite for tests

## Directory structure

```
app/Livewire/           NotesIndex, NoteShow, NoteForm, ApiTokens, Admin/Users + Teams + ImportNotes
app/Mcp/Servers/        DevnotesServer (instructions + tool registration)
app/Mcp/Tools/          AddNote, SearchNotes, GetNote
app/Http/Controllers/   Api/V1/NoteController + ExportController, Admin/ExportController, Auth/SSOController
app/Jobs/               ImportNotes (export/import section)
app/Console/Commands/   ImportNotesCommand (devnotes:import)
app/Models/             User, Note, OAuthUser (see auth section)
routes/                 web.php, api.php, ai.php (MCP), sso-auth.php
config/                 sso.php (access gate), mcp.php (OAuth redirect rules)
```

## Domain model

```
User 1 ──→ * Note   (Note soft-deletes; user_id survives; #code links keep resolving)
User * ──→ * Team   (team_user pivot: subscribed + note_default booleans)
Note * ──→ * Team   (note_team pivot)
OAuthUser = the same users table through a different lens (Passport only)
```

Models use PHP attribute style (`#[Fillable]`), not `$fillable` arrays. Every note mints two identities at creation (see the export/import section): a five-char `code` (`abq4x`, the only reference humans see) and a `ulid` (machine identity, never displayed); the integer id is internal plumbing. `Note::rendered_body` converts markdown via CommonMark with `html_input => escape`, and a mention extension turns `#abq4x` into a link to that note (lowercase-only, exact length - the pattern carries a `(?-i:` group because commonmark's inline parser matches case-insensitively by default). Notes are wiki-style: any authenticated user can edit or delete any note. Users are the gate; there are no per-note permissions.

### Teams scope search recall, never visibility

Design and rejected alternatives live in ant ADR devnotes-sXVTv. The whole feature is two pivots plus a filter:

- `Note::searchScoped($user, $query, $broader)` is the single scoping implementation, used by the API, the MCP tool, and the web index. It shows notes sharing a subscribed team plus teamless notes; `broader` (or a user with no subscriptions) means the whole pot. Its query callback carries the eager loads - never chain `->query()` onto it, Scout's `Builder::query()` replaces the callback rather than composing, which silently drops the scoping.
- `Note::assignTeams($ids)` attaches explicit teams or the author's `defaultNoteTeams` when given null - create paths only; update paths sync explicitly.
- `User::syncTeamPreferences($readIds, $defaultIds)` reconciles the pivot from the two checkbox groups; a team in neither list loses its row.
- Browsing, note show pages, and `#code` resolution are never scoped. Deleting a team cascades its pivot rows, so its notes join the whole pot - nothing is ever hidden.

## Export and import

Design and rejected alternatives live in ant ADR devnotes-9X77J (`ant for devnotes-gbHJd.8`); the identity pivot that reshaped it is ant entry devnotes-C8ggs (`ant for devnotes-gbHJd.9`).

- The export format is a versioned contract: `{"version": 1, "notes": [...]}`, shaped by `ExportNoteResource` (deliberately separate from `NoteResource` so the API can evolve without breaking import compatibility) and pinned byte-for-byte by `tests/fixtures/export-v1.json`. Each note carries its `ulid` and `code` - never the integer id, which is meaningless outside its install. `Note::exportPayload()` builds it over `withTrashed()`; `Note::EXPORT_JSON_FLAGS` makes every download byte-identical to the fixture's encoding. Authors travel as email (the matching key) plus names and staff/admin flags; passwords and tokens never travel.
- Both export doors are admin-only via the `admin` gate: an Export button on the notes index at `/admin/export` and `GET /api/v1/export` for scheduled DR pulls.
- `ImportNotes` (queued job, `$tries = 1`) takes `(disk, path, createUsers = true, fallbackOwner = null)`. Matching is by ulid: known ulids are skipped and reported by code - re-imports are idempotent, which is also the recovery path for a half-failed import (no transaction, deliberately). New notes get fresh internal ids; a new note whose code is already taken gets a re-minted code, reported in `recoded`. Existing users are matched by email and never modified; unknown authors are created (or, with `createUsers: false`, their notes go to the fallback owner). Codes, ulids, timestamps, and `deleted_at` are preserved via `forceFill` with timestamps off; trashed notes are created inside `withoutSyncingToSearch` so external search engines never index them.
- The job deletes its stored working copy in a `finally` - the caller's original file is never touched. `handle()` returns a report array; callers that want it must run the job in-process (`(new ImportNotes(...))->handle()`) because `dispatchSync` discards a `ShouldQueue` job's return value.
- `devnotes:import {file}` copies the file to the default disk and runs the job in-process. Because imports never insert explicit ids, no database sequence can desync and there is no raw SQL anywhere - the previously-agreed Postgres `setval` exception was deleted with the identity pivot.
- The web import (`/admin/import`, Livewire, flux:file-upload dropzone) previews in the request (envelope validation only - the job stays the source of truth), then stores the blob via `Storage` and dispatches the job to the real queue with the admin's per-note skip/overwrite decisions as `overwriteUlids` and, when "create missing authors" is off, the acting admin as fallback owner. Ulid matches that are IDENTICAL to the file (same title, body, teams, author, timestamps, deleted state) are silently counted and skipped - only drifted matches ask for a decision, with a "differs in" hint (a decision with one sane answer trains people to stop reading). Overwrite takes the file's version wholesale except `ulid`/`code`, which never change. The preview is a snapshot, deliberately - no locking between preview and job run.

## The auth story (the fiddly bit)

Three front doors, three auth mechanisms, one users table:

| Door | Auth | Guard/provider |
|------|------|----------------|
| Web UI | Session (SSO via Keycloak, or local form when `SSO_ENABLED=false`) | `web` / `users` |
| `/api/v1` | Sanctum bearer tokens (self-service on the API tokens page) | `sanctum` / `users` |
| `/mcp` | Passport OAuth 2.1 (discovery + dynamic client registration) | `api` / `oauth_users` |

### Why OAuthUser exists

Sanctum and Passport both add token methods to the user model via traits named `HasApiTokens`, and the method signatures are incompatible (`createToken()`, `tokens()`, `withAccessToken()` differ in parameters and return types). One class cannot hold both, and a subclass of `User` fatals because PHP enforces signature compatibility on the trait override. So:

- `User` keeps Sanctum's trait. The web UI and API are untouched.
- `OAuthUser` extends the framework's base user class directly (not `User`), uses Passport's trait, implements `OAuthenticatable`, and points at the same `users` table. Only the `oauth_users` auth provider references it.
- The browser approve step in the OAuth flow only calls `getAuthIdentifier()` on the session user, so `User` needs no Passport awareness at all.
- MCP tools receive `$request->user()` as an `OAuthUser`. It has no relationships; tools use `$request->user()->id` rather than `$user->notes()`.

### Passport specifics

- `Mcp::oauthRoutes()` in `routes/ai.php` serves the `.well-known` metadata and `POST /oauth/register`.
- The approve page is the published `resources/views/mcp/authorize.blade.php`, rewritten with Flux components (the vendor version used foreign design tokens and was illegible in dark mode).
- Keys come from `php artisan passport:keys`, once per environment, gitignored via `/storage/*.key`.
- `config/mcp.php` `redirect_domains` is a deliberate `'*'` during development. It must become an allowlist before any internet-reachable deploy, keeping `http://localhost` for CLI loopback callbacks. Tracked with a pinning-test recipe in the issue tracker.

### Testing the guard

Passport's guard constructs its crypto keys even to reject a tokenless request, so `tests/Feature/McpServerTest.php` generates an in-memory RSA keypair once per process (`passportTestKeys()`) and injects it via config. No key files are committed. `Passport::actingAs()` bypasses the guard entirely; the one real-token test (personal access client + `createToken`) exists precisely because of that, and is the only test that exercises the `oauth_users` provider mapping for real.

## MCP server

`DevnotesServer` builds its instructions per authenticated user at request time (`createContext()` override - the `#[Instructions]` attribute would beat the `$instructions` property, so there is no attribute): a short habit block (search before debugging, capture gotchas, write for a stranger, retry scoped searches with broader) followed by a digest of the 10 most recently updated notes the caller would see in a default-scoped search, one `#code title (teams, age)` line each, plus the pot count. The digest is the session-start surfacing mechanism: every MCP surface gets recent team knowledge pushed into context at connection time, no hooks or CLI needed (ADR devnotes-7qsQV). and three tools with token-frugal returns: `search-notes` gives code/title/200-char snippet (max 20 hits) scoped to the caller's teams by default - scoped responses carry a retry-with-`broader: true` hint, and broader responses label out-of-team rows `from_outside_your_teams` - `get-note` gives the full markdown and accepts `abq4x` or `#abq4x`, `add-note` validates with the same rules as the API, tags the note with team names or the author's defaults, and returns `{code, title}`. Unknown codes return a tool error pointing at `search-notes`, not an exception. Tools resolve the caller's `App\Models\User` via `User::findOrFail($request->user()->id)` - same table, same ids as OAuthUser.

Operational quirks learned the hard way: laravel/mcp 0.9 speaks protocol revisions up to 2025-11-25 (clients negotiate down), and MCP clients cache both the tool list and the instructions text at connection time. After deploying new or changed tools, clients need a manual reconnect; changed instructions may need the connector removed and re-added.

## Routes overview

| Route | Handler | Access |
|-------|---------|--------|
| `/` | NotesIndex (Livewire) | auth |
| `/notes/{note:code}` | NoteShow | auth |
| `/settings/api-tokens` | ApiTokens | auth |
| `/settings/teams` | TeamSettings | auth |
| `/admin/users` | Admin/Users | `can:admin` |
| `/admin/teams` | Admin/Teams | `can:admin` |
| `/admin/export` | Admin/ExportController | `can:admin` |
| `/admin/import` | Admin/ImportNotes (Livewire) | `can:admin` |
| `/api/v1/notes` (apiResource) | Api/V1/NoteController | `auth:sanctum` |
| `/api/v1/teams` (index only) | Api/V1/TeamController | `auth:sanctum` |
| `/api/v1/export` | Api/V1/ExportController | `auth:sanctum` + `can:admin` |
| `/mcp` | DevnotesServer | `auth:api` (Passport) |
| `/.well-known/oauth-*`, `/oauth/register` | laravel/mcp | public (by spec) |
| `/login`, `/login/sso`, `/auth/callback` | SSOController | guest |

API routes are name-prefixed `api.v1.*` because a bare `apiResource('notes')` stole the web `notes.show` route name and web pages silently generated API URLs.

## Authorization

`is_admin` boolean drives a single `admin` gate (defined in `AppServiceProvider`). Admins manage users; everyone else just uses notes. The SSO gate (`config/sso.php`) controls who can get in at all: `autocreate_new_users`, `allow_students`, `admins_only`.

## Testing

- Pest 5, feature tests, in-memory sqlite via `RefreshDatabase`. No migrations or seeders needed first.
- `phpunit.xml` pins `SCOUT_DRIVER`, `FILESYSTEM_DISK`, and all `SSO_*` keys because `.env` leaks into any test-env key it does not pin. If a test fails oddly on config, suspect a leak first.
- Tests pin `SCOUT_DRIVER=collection` because sqlite cannot run the production full-text search (`SearchUsingFullText` on `Note` + the guarded full-text index migration). The collection engine filters in PHP, so the real `whereFullText` query path is only exercised by live verification on MySQL/Postgres.
- House style: assert side-effects through Eloquent models, cover happy and sad paths together, one behaviour per test.
- Run: `php artisan test --compact`

## Local development

- `lando start`, then `lando mfs` (migrate:fresh + TestDataSeeder).
- Seeded logins: `admin2x` / `secret` (admin), `user2x` / `secret` (standard).
- `SSO_ENABLED=false` gives the local login form.
- A vite watcher usually runs during dev; CSS/JS changes lag a few seconds.
