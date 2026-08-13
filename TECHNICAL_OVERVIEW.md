# Technical overview

Last updated: 2026-08-13

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
app/Livewire/           NotesIndex, NoteShow, NoteForm, ApiTokens, Admin/Users
app/Mcp/Servers/        DevnotesServer (instructions + tool registration)
app/Mcp/Tools/          AddNote, SearchNotes, GetNote
app/Http/Controllers/   Api/V1/NoteController, Auth/SSOController
app/Models/             User, Note, OAuthUser (see auth section)
routes/                 web.php, api.php, ai.php (MCP), sso-auth.php
config/                 sso.php (access gate), mcp.php (OAuth redirect rules)
```

## Domain model

```
User 1 ──→ * Note   (Note soft-deletes; user_id survives; #id links keep resolving)
OAuthUser = the same users table through a different lens (Passport only)
```

Models use PHP attribute style (`#[Fillable]`), not `$fillable` arrays. `Note::rendered_body` converts markdown via CommonMark with `html_input => escape`, and a mention extension turns `#123` into a link to that note. Notes are wiki-style: any authenticated user can edit or delete any note. Users are the gate; there are no per-note permissions.

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

`DevnotesServer` carries a short instructions block (search before debugging, capture gotchas, write for a stranger) and three tools with token-frugal returns: `search-notes` gives id/title/200-char snippet (max 20 hits), `get-note` gives the full markdown and accepts `49` or `#49`, `add-note` validates with the same rules as the API and returns `{id, title}`. Unknown ids return a tool error pointing at `search-notes`, not an exception.

Operational quirks learned the hard way: laravel/mcp 0.9 speaks protocol revisions up to 2025-11-25 (clients negotiate down), and MCP clients cache both the tool list and the instructions text at connection time. After deploying new or changed tools, clients need a manual reconnect; changed instructions may need the connector removed and re-added.

## Routes overview

| Route | Handler | Access |
|-------|---------|--------|
| `/` | NotesIndex (Livewire) | auth |
| `/notes/{note}` | NoteShow | auth |
| `/settings/api-tokens` | ApiTokens | auth |
| `/admin/users` | Admin/Users | `can:admin` |
| `/api/v1/notes` (apiResource) | Api/V1/NoteController | `auth:sanctum` |
| `/mcp` | DevnotesServer | `auth:api` (Passport) |
| `/.well-known/oauth-*`, `/oauth/register` | laravel/mcp | public (by spec) |
| `/login`, `/login/sso`, `/auth/callback` | SSOController | guest |

API routes are name-prefixed `api.v1.*` because a bare `apiResource('notes')` stole the web `notes.show` route name and web pages silently generated API URLs.

## Authorization

`is_admin` boolean drives a single `admin` gate (defined in `AppServiceProvider`). Admins manage users; everyone else just uses notes. The SSO gate (`config/sso.php`) controls who can get in at all: `autocreate_new_users`, `allow_students`, `admins_only`.

## Testing

- Pest 5, feature tests, in-memory sqlite via `RefreshDatabase`. No migrations or seeders needed first.
- `phpunit.xml` pins `SCOUT_DRIVER` and all `SSO_*` keys because `.env` leaks into any test-env key it does not pin. If a test fails oddly on config, suspect a leak first.
- House style: assert side-effects through Eloquent models, cover happy and sad paths together, one behaviour per test.
- Run: `php artisan test --compact`

## Local development

- `lando start`, then `lando mfs` (migrate:fresh + TestDataSeeder).
- Seeded logins: `admin2x` / `secret` (admin), `user2x` / `secret` (standard).
- `SSO_ENABLED=false` gives the local login form.
- A vite watcher usually runs during dev; CSS/JS changes lag a few seconds.
