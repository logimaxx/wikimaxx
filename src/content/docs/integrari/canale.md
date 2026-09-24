---
title: Canale (WooCommerce / eMAG)
description: Cum ajung comenzile din magazin și marketplace în MaxxOps.
---

## Principiu

Canalele de vânzare sunt **adaptoare**: WooCommerce și eMAG sunt cele livrate azi. Alte magazine / marketplace-uri = același tip de conector.

## WooCommerce

- Comanda din shop ajunge în MaxxOps (webhook; polling ca rezervă).
- Catalog și **push stoc** înapoi în shop din snapshot-ul MaxxOps (cu mapări și flag-uri pe canal).

## eMAG

- Import comenzi (poll) când e **activat** pe canal.
- Mapare oferte / catalog înainte — o ofertă nemapată blochează doar comanda respectivă.

## Pentru utilizator

Comenzile din canale apar în **Vânzări → Comenzi** ca orice altă comandă. Nu copiezi date din admin-ul Woo în Excel.

Configurarea canalelor pe firmă: Tech admin → Integrări / Canale.

## Clientul final

Nu folosește MaxxOps — rămâne pe shop / marketplace; MaxxOps rulează în spate pentru echipă.
