## Development

```bash
composer install
cp .env.example .env   # SESSION_SECRET; CONTENT_ROOT → your Markdown tree
php bin/migrate-content.php   # optional demo from seed/
php bin/seed-admin.php
php -S 127.0.0.1:8080 -t public public/router.php
```

PHP ≥ 8.2. Runtime content: `CONTENT_ROOT` (often a separate docs repo). Optional `LIBRARIES_PATH` mounts several trees at `/{id}/`; without that file, `CONTENT_ROOT` stays at `/`. Demo seed in git: `seed/`.

## What this app is

WikiMaxx is a **generic** flat-file wiki engine (Markdown + folder ACL + admin). Product-specific help (e.g. MaxxOps) belongs in a content repository, not here.

Namespace: `WikiMaxx\`.

## ACL

Nearest `_access.json` walking up to content root wins. Missing ACL → public. Nav hides what the current user cannot read.

Roles come from `data/roles.json` (see `ROLES_PATH`). Soft-FK: unknown ids rejected on user/ACL write; orphans dropped at read; delete blocked if still referenced.

## Nav order

Optional `_order.json` in a folder: JSON array of child folder names / page slugs. Absent → dirs then files, each A–Z by title. In the UI: **Ordonează** on a page (when you can edit) or Admin → **ordine** / `/admin?order=`. **Redenumește** on a folder or page changes the directory name or `.md` filename. **Redenumește** on a folder or page changes the directory name or `.md` filename.