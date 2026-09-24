---
title: Stoc insuficient
description: Ce se întâmplă când firma nu are stoc — hold, altă firmă, transfer.
---

## La verificare stoc

După confirmare (sau după producție, la produse compuse), MaxxOps verifică cantitățile pe liniile de vânzare.

## Dacă lipsește pe firma curentă

1. **Altă firmă din instanță are stoc?** — se poate crea automat o **comandă copil** pe firma care aprovizionează (transfer inter-company). Comanda părinte așteaptă finalizarea pe copil, apoi continuă.
2. **Nicăieri?** — comanda rămâne pe insuficient / hold, cu mesaj vizibil. Nu se facturează „din aer”.

## Ce faci tu

- Depozit / vânzări: citești mesajul pe comandă.
- Completezi stocul (recepție / gestiune) sau aștepți transferul.
- Reluezi procesarea când e cazul.

## Multi-firmă — pe scurt

Fiecare firmă are adaptoarele și gestiunile ei. Cărțile rămân separate; echipa nu mai ține transferurile interne doar în Excel.
