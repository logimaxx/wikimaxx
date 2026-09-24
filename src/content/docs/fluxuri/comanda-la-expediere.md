---
title: Comandă → expediere
description: Viața unei comenzi MaxxOps — de la captare la finalizat.
---

## Schema pe scurt

```
Captare (Woo / eMAG / staff / kiosk)
        ↓
  Ofertă DRAFT (opțional: produs compus)
        ↓
   Confirmare
        ↓
  [producție?] → Da în producție → atelier → apoi stoc
        ↓
  Verificare stoc
        ↓
   Stoc OK  /  Lipsă → altă firmă? / hold
        ↓
  Picking
        ↓
  [lucrare la client?] → Finalizează lucrarea
        ↓
  Factură client
        ↓
  Livrare?
   ├─ Nu  → Finalizat
   └─ Da  → AWB → Pregătit → Expediat → Finalizat
```

## Reguli pe care le simți

- O comandă = **o firmă** de facturare.
- Verificarea de stoc e pe liniile de vânzare (produs / consumabil).
- Comenzile cu produse compuse ajung la verificare stoc **după** producție (când e cazul), nu imediat la confirmare.
- **Picking înainte de factură.**
- Lucrarea la client, livrarea și factura pot fi opționale pe comandă / profil.

## De unde intră comenzile

| Canal | Ce se întâmplă |
|-------|----------------|
| **WooCommerce** | Comanda din shop ajunge în MaxxOps |
| **eMAG** | Import (când poll-ul e activat pe canal) |
| **Staff** | Ofertă / comandă creată manual |
| **Kiosk** | Coș → import DRAFT |

Toate alimentează **același** model de comandă.

## Pagini pe pași

- [Factură](/fluxuri/factura/)
- [AWB / livrare](/fluxuri/awb/)
- [Stoc insuficient](/fluxuri/stoc-insuficient/)

## Video

Clip pe fluxul complet: [Tutoriale video](/video/).
