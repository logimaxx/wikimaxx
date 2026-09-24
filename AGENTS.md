## Development

```
npm run dev
```

Prefer Node.js ≥ 22 (`nvm use 22`).

## Content rules (MaxxOps Academy)

Two audiences in one site:

1. **Pentru utilizatori** (`pornire/`, `pe-rol/`, `fluxuri/`, `integrari/`, `video/`) — help for client staff; safe to leave public.
2. **Echipă** (`echipa/`) — onboarding, sales pitch, strategies, collaborators. Always set `robots: noindex` in frontmatter. Do not put secrets (passwords, API keys). Remind deployers to protect `/echipa/` with Basic Auth on the host.

- User docs: no PHP paths / schema / cron internals.
- Sales docs: honest limits; align with maxxops.ro messaging.
- Do not reintroduce full client overview into `maxxops_latest/documentation/maxxops-perspectiva-client.md`.

## Documentation

Starlight: https://starlight.astro.build/
