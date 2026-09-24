---
title: Administrator firmă
description: Configurare firmă, utilizatori, catalog și verificări înainte de go-live.
---

## Ce faci tu

Configurezi datele firmei, taxe / UM / serii / FX, utilizatorii, regulile de pontaj. Gestiunile le gestionezi din **Depozit**. Modulele licențiate și credențialele SmartBill / GLS / canale rămân adesea la **vendor (Tech admin)** — verifică cine are acces pe instanța ta.

## Zi tipică (setup)

1. Date firmă și setări de bază.
2. Catalog: produse (inclusiv **produse compuse** cu rețetă + parametri) pentru ofertare.
3. Utilizatori și roluri.
4. Verifică împreună cu vendorul că **integrările răspund** înainte de go-live.
5. Monitorizează blocajele (status integrări, loguri — Tech admin) când apar probleme.

## Ce nu configurezi tu (de obicei)

- Credențiale SmartBill / GLS / Woo / eMAG — **Tech admin → Firme → Integrări / Canale**
- Setări procesare pe întreaga instanță (mock, batch etc.) — Tech admin
- Telegram / ANAF pe instanță — Tech admin

## Roluri utile

vendor, admin, operator, manager, accountant, viewer — fiecare cu alt nivel de acces.

## Următorul pas

- [Integrări — SmartBill](/integrari/smartbill/)
- [Canale Woo / eMAG](/integrari/canale/)
