---
title: Depozit / operațiuni
description: Picking pe comandă, transferuri și pregătire expediere.
---

## Ce faci tu

Verifici stocul pe comenzi, faci **picking pe comandă**, pregătești expedierea, urmărești transferurile între firme. Pe gestiune nativă: NIR, facturi furnizor, consum, inventar.

## Zi tipică

1. Deschide comenzi gata de cules (stare **Picking**).
2. Confirmă cantitățile culese pe detaliul comenzii.
3. Pregătește coletul când există AWB / etichetă PDF.
4. Urmărește expedierea până la finalizare.

## Unde e picking-ul

**Nu** există meniu separat „Picking”. Confirmarea se face din **detaliul comenzii** aflate în starea corespunzătoare.

Transferurile generate de pipeline: **Depozit → Transferuri comenzi**.

## Gestiune internă vs externă

| Mod | Cine deține cantitățile |
|-----|-------------------------|
| **Gestiune internă (nativă)** | MaxxOps — documente + mișcări în aplicație |
| **Sistem extern (ex. SmartBill)** | Softul de contabilitate; MaxxOps oglindește / rezervă pentru comenzi |

Fluxul de comandă (verificare → picking → factură → livrare) e același; diferă sursa de adevăr pe cantități.

## Stoc insuficient

Vezi [Stoc insuficient](/fluxuri/stoc-insuficient/) — inclusiv comanda copil pe altă firmă.

## Video

Legături pe [Tutoriale video](/video/).
