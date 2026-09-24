# MaxxOps Academy

Documentație MaxxOps pe **două zone**:

| Zonă | Public | Conținut |
|------|--------|----------|
| **Pentru utilizatori** | Da (help clienți) | Pornire, pe rol, fluxuri, integrări, video |
| **Echipă (intern)** | Nu ideal — protejează pe host | Onboarding, sales, strategii, colaboratori |

Site static: [Astro Starlight](https://starlight.astro.build/).  
URL: **https://academy.maxxops.ro**

Conținutul se scrie **doar intern** (acest repo). Fără editare publică tip wiki.

## Comenzi

Necesită **Node.js ≥ 22**.

```bash
npm install
npm run dev      # http://localhost:4321
npm run build    # output în dist/
npm run preview
```

## Structură

```
src/content/docs/
  pornire/ pe-rol/ fluxuri/ integrari/ video/   # utilizatori
  echipa/
    onboarding/   # bun venit, prima săptămână, colaboratori
    sales/        # produs, ICP, obiecții, strategii
```

## Securitate — zona Echipă

Build-ul static pune **tot** în `dist/`. `noindex` pe paginile `/echipa/` nu egalează autentificare.

Pe webhost, configurează **HTTP Basic Auth** (sau echivalent) pe calea `/echipa/` (și ideal pe search index dacă e nevoie). Nu pune în git parole, prețuri sensibile fără această protecție.

Exemplu Apache (ilustrativ):

```apache
<Location /echipa>
  AuthType Basic
  AuthName "MaxxOps Echipa"
  AuthUserFile /path/to/.htpasswd
  Require valid-user
</Location>
```

## Ce nu e aici

Arhitectură EN, reference schema, ops, roadmap → repo MaxxOps `documentation/`.

## Deploy

1. `npm run build`
2. Rsync/FTP `dist/` pe docroot `academy.maxxops.ro`
3. DNS + HTTPS
4. Basic Auth pe `/echipa/`

```bash
npm run build
rsync -avz --delete dist/ user@host:/var/www/academy.maxxops.ro/
```
