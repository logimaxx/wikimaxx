---
title: Facturare (SmartBill)
description: Ce face MaxxOps cu facturarea — și ce rămâne la SmartBill.
---

## Principiu

MaxxOps nu e legat de un singur soft de facturare. Fluxul vorbește cu un **rol** (facturare); pe firmă se leagă un **adaptor**. Azi e livrat **SmartBill** — alte sisteme = alte adaptoare, același flux de comenzi.

## Ce rămâne la SmartBill

- Numerotarea facturilor
- Contabilitatea / evidența legală pe documente
- Stocul legal, **dacă** gestiunea firmei e pe adaptorul SmartBill (nu pe gestiunea nativă MaxxOps)

## Ce face MaxxOps

- Pregătește factura din comandă / contract
- O împinge către SmartBill
- Ține statusul de sincronizare și PDF-ul când e disponibil

## Pentru utilizator

Nu configurezi API-ul din ziua operațională — asta e la Tech admin. Tu urmărești **Vânzări → Facturi** și mesajele de pe comandă dacă sync-ul eșuează.

Vezi și [fluxul Factură](/fluxuri/factura/).
