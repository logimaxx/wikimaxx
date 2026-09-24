---
title: Factură
description: Cum ajunge factura din MaxxOps în sistemul de contabilitate.
---

## Ideea

MaxxOps **pregătește** factura din comandă (sau din contract) și o **împinge** către serviciul de facturare configurat pe firmă (azi tipic **SmartBill**). Numerotarea și contabilitatea rămân acolo.

## Când se emite

După **picking** (și, dacă e cazul, după **Finalizează lucrarea** pe teren). Nu facturezi înainte să confirmi ce s-a cules / ce s-a făcut la client.

## Ce vezi tu

- **Vânzări → Facturi** — listă, status sincronizare, PDF când e disponibil.
- Pe comandă: starea trece prin așteptare factură → facturată.

## Dacă ceva eșuează

Comanda rămâne cu mesaj vizibil (hold / eroare). Remediezi cauza (date client, credențiale, serie etc.) și **reiei** — comanda nu „dispare”.

Detalii adaptor: [Facturare (SmartBill)](/integrari/smartbill/).

## Contracte

Dacă modulul **Contracte** e activ: milestone / retainer / ore extra pot genera facturi din program (automat), pe același adaptor ca la comenzi. Nu e abonament de magazin online.
