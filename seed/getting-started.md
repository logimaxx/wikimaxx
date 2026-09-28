---
title: Getting started
description: Point CONTENT_ROOT at your docs and run the PHP app
---

# Getting started

1. Copy `.env.example` to `.env` and set `SESSION_SECRET`.
2. Set `CONTENT_ROOT` to your Markdown tree (this demo uses `seed/` via `migrate-content`, or a sibling docs repo).
3. `composer install` → `php bin/seed-admin.php` → `php -S 127.0.0.1:8080 -t public public/router.php`.

## Content layout

```
CONTENT_ROOT/
  _access.json      # optional: title, public, roles
  index.md
  getting-started.md
  internal/
    _access.json    # public: false
    index.md
```

Nearest `_access.json` walking up from a page wins. Missing ACL → public.
