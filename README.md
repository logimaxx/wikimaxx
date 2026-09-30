# WikiMaxx

Flat-file **wiki / documentation engine**: scans a Markdown tree, builds navigation, renders pages, optional ACL by role, in-browser admin edit.

Content lives outside the app (or in a separate docs repo). Set `CONTENT_ROOT` in `.env`.

## Requirements

- PHP ≥ 8.2
- Composer
- Extensions: `json`, `session`, `mbstring` (users stored as JSON — no SQLite required)

## Setup

```bash
composer install
cp .env.example .env
# set SESSION_SECRET; set CONTENT_ROOT to your site (or keep demo path)

php bin/migrate-content.php   # optional: copy seed/ → CONTENT_ROOT + demo ACL
php bin/seed-admin.php

php -S 127.0.0.1:8080 -t public public/router.php
```

Open http://127.0.0.1:8080 — admin from `.env`.

### Example: MaxxOps docs (sibling checkout)

```env
CONTENT_ROOT=../maxxops_documentation/wiki
```

## Layout

```
public/           # docroot
src/              # WikiMaxx\ (Auth, Acl, ContentTree, …)
templates/
seed/             # minimal demo content (git)
content/          # default local CONTENT_ROOT (gitignored) if you migrate seed here
data/users.json   # accounts (gitignored)
data/roles.json   # role catalog (gitignored; seeded on first boot)
bin/              # migrate-content.php, seed-admin.php
```

### Roles (`roles.json`)

Catalog of access levels + optional write capability. Default builtins: `admin`, `colaborator`, `partener`, `editor` (`capability: write`). Manage in `/admin/roles`.

`users.json` and `_access.json` store role **ids** only — no FK; admin write-paths reject unknown ids, and delete is blocked while a role is still used.

### ACL (`_access.json`)

```json
{
  "title": "Internal",
  "public": false,
  "roles": ["colaborator", "admin"]
}
```

Applies to the folder and descendants until another `_access.json`. No file on the path → public.

Folder URLs canonicalize with a trailing slash (`/guide/` → so relative Markdown links resolve). Dir entries in nav use the same form.

**Special ids:** `admin` (bypass + users/roles/ACL admin); any role with `capability: write` (default `editor`) may edit content on zones it can read.

### Nav order (`_order.json`)

Per-folder list of child folder names and page slugs (no `.md`):

```json
["getting-started", "internal", "faq"]
```

Missing file → folders A–Z, then pages A–Z (by title). Entries not listed appear after the ordered ones.

In the UI (write role): link **Ordonează** on a page, or Admin → **ordine** on a folder / „Ordine rădăcină”. Drag rows, then save.
### Admin

After login with write capability: `/admin` — browse folders level-by-level, Markdown edit, image upload, per-level nav order. Only `admin`: `_access.json`, `/admin/users`, `/admin/roles`.

## Deploy

1. Clone + `composer install --no-dev`
2. Docroot → `public/`
3. Volume for `CONTENT_ROOT` and `data/` **outside** `public/`
4. `.env` on the host (or `dotenv` — used by Hestia installs; `.env` wins if both exist)
5. Optionally seed admin once; content comes from your docs repo / volume

Example nginx: [deploy/nginx.example.conf](deploy/nginx.example.conf)

### Hestia git-deploy

Panel / `config.conf`:

| Key | Value |
|---|---|
| `INSTALL_CMD` | `bash .hestia-install.sh` |
| `OUTPUT_DIR` | `.` |
| `EXCLUDE` | `.git,.env,.env.*,node_modules,tests,.github` |

`secrets.env` (optional but useful):

```bash
ADMIN_USER=admin
ADMIN_PASSWORD=<initial>
ADMIN_EMAIL=you@example.com
# optional — auto-generated on first deploy if omitted:
# SESSION_SECRET=<long-random>
# CONTENT_ROOT=/absolute/path/to/wiki
# SEED_CONTENT=yes
```

After the first successful deploy, set the domain **Custom document root → Directory** to `public` (Hestia UI or `v-change-web-domain-docroot USER DOMAIN DOMAIN public`).

Runtime state lives in `private/wikimaxx-runtime/` under the domain (`dotenv`, `data/`, default `content/`). On later deploys the install script **reuses** that env: it prefers the live `public_html/dotenv` (so manual edits survive), then `private/wikimaxx-runtime/dotenv`, then the legacy `wikimaxx-runtime/dotenv`. Only `FORCE_ENV_REFRESH=1` in `secrets.env` regenerates from secrets (and resets `SESSION_SECRET` if you did not set one).

Edit either `public_html/dotenv` or `private/wikimaxx-runtime/dotenv` — do not put secrets only in `secrets.env` after the first deploy unless you force a refresh.
