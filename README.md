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
bin/              # migrate-content.php, seed-admin.php
```

### ACL (`_access.json`)

```json
{
  "title": "Internal",
  "public": false,
  "roles": ["colaborator", "admin"]
}
```

Applies to the folder and descendants until another `_access.json`. No file on the path → public.

**Roles:** `admin` (everything + users + ACL), `colaborator`, `partener`, plus write capability `editor`. Customize roles in `src/Roles.php` as needed.

### Admin

After login with `admin` / `editor`: `/admin` — folders, Markdown pages, image upload. Only `admin`: edit `_access.json` and `/admin/users`.

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

`secrets.env` (first deploy at least):

```bash
SESSION_SECRET=<long-random>
ADMIN_USER=admin
ADMIN_PASSWORD=<initial>
ADMIN_EMAIL=you@example.com
# optional:
# CONTENT_ROOT=/absolute/path/to/wiki
# SEED_CONTENT=yes
```

After the first successful deploy, set the domain **Custom document root → Directory** to `public` (Hestia UI or `v-change-web-domain-docroot USER DOMAIN DOMAIN public`).

Runtime state lives in `private/wikimaxx-runtime/` under the domain (dotenv, `data/`, default `content/`) and is reused on later deploys.
