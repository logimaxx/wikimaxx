# WikiMaxx

Flat-file **wiki / documentation engine**: scans a Markdown tree, builds navigation, renders pages, optional ACL by role, in-browser admin edit.

Content lives outside the app (or in a separate docs repo). One tree: set `CONTENT_ROOT`. Several trees: set `LIBRARIES_PATH` to a JSON registry — see [Several libraries](#several-libraries). Without that file, `CONTENT_ROOT` is the whole site at `/`.

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

### Several libraries

One WikiMaxx process can serve several Markdown trees. Each tree is a **library**, mounted at `/{id}/`. Login, users, and roles stay one catalog for the whole site.

Copy [libraries.example.json](libraries.example.json) to `libraries.json` (gitignored) and set `LIBRARIES_PATH=libraries.json`. The path may be relative to the app root or absolute. Multi-library mode starts only when that file exists. If `LIBRARIES_PATH` is unset or the file is missing, the site is the single `CONTENT_ROOT` tree at `/`, and `CONTENT_ROOT` is created on first use. While the registry file is present, `CONTENT_ROOT` is not read.

```json
[
  { "id": "maxxops", "title": "MaxxOps", "path": "../maxxops_documentation/wiki" },
  { "id": "runbooks", "path": "/var/docs/runbooks" }
]
```

The file is a JSON array. Order in the file is the order of the catalog, the home sidebar, and the library switcher. An empty array serves an empty catalog — list at least one library. A bad file fails at startup (the site does not fall back to `CONTENT_ROOT`):

| Problem | Result |
|---|---|
| Not a JSON array, or an entry is not an object | `LIBRARIES_PATH must be a JSON array` / `Library entry N is not an object` |
| `id` missing or not `[a-z0-9][a-z0-9-]*` | `Invalid library id` |
| `id` is `login`, `logout`, `search`, `admin`, or `assets` | `Reserved library id` (those paths are app routes) |
| Same `id` twice | `Duplicate library id` |
| `path` missing, or not an existing directory | `Library path missing` / `Library path is not a directory` |

`path` is resolved like `CONTENT_ROOT`: absolute, or relative to the app root. The directory must already exist; libraries are not created for you.

`title` is optional. When it is omitted or blank, the label is the `title` in that library's root `_access.json`, then the id with hyphens turned into words (`run-books` → `Run Books`).

#### URLs

| URL | What it is |
|---|---|
| `/` | Catalog of libraries this reader may open. Title **Biblioteci**. A reader who can open none of them sees an empty list. |
| `/{id}/` | That library's home: `index.md` if present, otherwise a folder listing. `/maxxops` redirects to `/maxxops/`. |
| `/{id}/guide/page` | Page or asset inside that tree. Folder URLs keep the trailing slash. |
| `/search` | One search box over every library this reader may open. |
| `/admin?lib={id}` | Content admin for that library. `/admin` with no `lib` asks which one. |
| `/login`, `/admin/users`, `/admin/roles` | Site-wide. Not inside a library. |

An unknown first segment (`/no-such-lib/…`) is a 404. The header brand on the catalog and on search is **WikiMaxx**; inside a library it is that library's title. The brand link always returns to `/`.

Breadcrumbs inside a library are **Acasă** (`/`) → library title → the page path.

#### What a reader sees

The catalog, the home sidebar, the in-library switcher, and search only include libraries whose **root** `_access.json` allows this reader (guest, or their roles). A library with no root `_access.json` is public, same as a single-tree site. A private root is hidden from people who cannot read it; they are not offered a link that would 401.

Inside a library the sidebar is a switcher (every library this reader may open, current one marked) plus that library's own nav. `_order.json` and `_access.json` apply only inside that tree. The ACL walk stops at the library root: a `_access.json` in a parent directory outside the tree is never consulted.

Search merges hits from those libraries, labels each hit with the library title, ranks them together, and returns the top 50.

#### Links inside a page

Markdown hrefs are not rewritten. Folder URLs end with `/`, so a relative link (`../fluxuri/factura`, `images/shot.png`) stays inside the library. A link that starts with `/` is from the site root, so it leaves the library (`/search`, `/runbooks/install`). To point at another library, use that form.

#### Admin

`/admin` lists every configured library, including ones the current editor cannot read at the root, and opens the chosen tree at `/admin?lib={id}`. Create, edit, upload, delete, nav order, and ACL edits stay inside that tree. Write checks still use that library's `_access.json`. **Utilizatori** and **Roluri** are global: one `data/users.json` and one `data/roles.json`. Deleting a role is blocked while any library's `_access.json` still names it.

#### Deploy

Put the registry on the host, outside `public/`, and point `LIBRARIES_PATH` at it with an absolute path (see [Hestia](#hestia-git-deploy)). The file is gitignored, so a release does not ship it. Each `path` needs its own volume, the same way `CONTENT_ROOT` does for a single tree.

## Layout

```
public/           # docroot
src/              # WikiMaxx\ (Auth, Acl, ContentTree, …)
templates/
seed/             # minimal demo content (git)
content/          # default local CONTENT_ROOT (gitignored) if you migrate seed here
libraries.json    # optional multi-library registry (gitignored)
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

After login with write capability: `/admin` — browse folders level-by-level, Markdown edit (toolbar, split source / live preview rendered with the same parser as the public page), image upload, per-level nav order. **Redenumește** changes a folder name or a page's `.md` filename (the slug), and updates the parent `_order.json` when one exists. The page title in frontmatter stays as written. With several libraries, `/admin` asks which tree, then stays on `/admin?lib={id}`. Only `admin`: `_access.json`, `/admin/users`, `/admin/roles` (users and roles are site-wide).

## Deploy

1. Clone + `composer install --no-dev`
2. Docroot → `public/`
3. Volumes for content and `data/` **outside** `public/`. Single tree: `CONTENT_ROOT`. Several libraries: the registry file plus each library `path`
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
# LIBRARIES_PATH=/absolute/path/to/libraries.json
# SEED_CONTENT=yes
```

After the first successful deploy, set the domain **Custom document root → Directory** to `public` (Hestia UI or `v-change-web-domain-docroot USER DOMAIN DOMAIN public`).

Runtime state lives in `private/wikimaxx-runtime/` under the domain (`dotenv`, `data/`, default `content/`). On later deploys the install script **reuses** that env: it prefers the live `public_html/dotenv` (so manual edits survive), then `private/wikimaxx-runtime/dotenv`, then the legacy `wikimaxx-runtime/dotenv`. Only `FORCE_ENV_REFRESH=1` in `secrets.env` regenerates from secrets (and resets `SESSION_SECRET` if you did not set one).

Edit either `public_html/dotenv` or `private/wikimaxx-runtime/dotenv` — do not put secrets only in `secrets.env` after the first deploy unless you force a refresh.
