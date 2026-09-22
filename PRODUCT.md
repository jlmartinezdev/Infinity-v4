# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Primary users are ISP staff operating the business in the office, at the cash desk, in the field, and during network incidents. Seeded roles are Administrador, Cajero, and Técnico; menus and actions are filtered by granular permissions, so operators never share one screen.

This repository’s current operator is Interplus (Paraguay). The subscriber (abonado) does not use this panel: they use WhatsApp and the Interplus Clientes app.

The product is intended for other ISPs in Paraguay and LatAm as well; white-label operator branding beyond the current Interplus deployment is undecided.

## Product Purpose

Infinity ISP is the staff operating system for an internet provider: it makes it possible to run clients, connectivity (FTTH and MikroTik), legal billing, collections, support, inventory, and subscriber messaging from one place instead of a SaaS WISP panel plus disconnected tools.

Success is that staff complete the day’s work—onboard, bill, collect, cut or restore service, repair, and answer the subscriber—without leaving Infinity or re-entering the same fact in another system.

## Positioning

Packaged for Paraguayan and LatAm ISPs: legal electronic invoicing (SIFEN e-Kuatia), local payments (TPago), and live optical/MikroTik control in the same product. A generic WISP CRM cannot truthfully claim that combination as one operational surface.

## Operating Context

Staff work at desks (caja, administración, atención) and in the field (técnicos with maps, NAP boxes, tickets, “en camino” WhatsApp). Recurring rituals include cobros and rendición de efectivo, facturas internas vs. electrónicas, promesas de pago, corte automático de servicio, monitoreo de APs/routers, and failover entre salidas ISP.

The subscriber relationship runs on WhatsApp Business and Interplus Clientes (portal API in this repo; native app is separate). Receipts and subscriber-facing copy use the operator brand, not the staff product name. Legacy data can be imported from WispHub.

Local development: Laravel (`php artisan serve`, typically `http://localhost:8000`) plus Mix/Webpack (`npm run watch` / `npm run dev:all`).

## Capabilities and Constraints

Confirmed in this codebase:

- Clients, pedidos, agenda, mapas; servicios PPPoE y Hotspot; cuentas TV streaming
- Facturación electrónica SIFEN, facturas internas, notas de crédito, cobros, recibos, links TPago, promesas de pago, rendición de efectivo, corte de servicio
- Tickets, tareas, WhatsApp (mensajes, plantillas, avisos operativos)
- Inventario (productos/equipos, compras, ventas, gastos, proveedores, cotización dólar)
- FTTH: OLTs, cajas NAP, mapa óptico, salidas PON, líneas de cable
- Red: routers MikroTik, pools de IP, APs wireless, alertas de caída, failover ISP, auditoría
- Loyalty / CMS and portal API for Interplus Clientes; avisos push
- Usuarios, roles, permisos por pantalla; importación WispHub

Terminology to preserve: caja NAP, OLT, PON, PPPoE, SIFEN, e-Kuatia, TPago, factura interna vs. electrónica, corte de servicio, promesa de pago, rendición de efectivo.

Undecided: multi-tenant / white-label for other ISP brands; whether Interplus Clientes remains the only subscriber app.

## Brand Commitments

- Staff UI product name: **Infinity ISP**. Do not rename the panel to Interplus.
- Subscriber-facing brand in this deployment: **Interplus** (recibos, WhatsApp, app Interplus Clientes). Do not put Infinity on subscriber receipts or chat.
- Voice: operational Spanish as used in the panel and Interplus customer copy; no English chrome unless the user asks.

## Evidence on Hand

Real operational data, copy, and flows live in the running app and `docs/` (APIs portal/loyalty/TPago, recibo Interplus, SIFEN, WhatsApp). Router/OLT diagrams live under `public/images/`. Do not invent testimonials, other ISP customers, pricing, or markets beyond Paraguay/LatAm.

## Product Principles

1. One operational truth: staff should not re-type a client, service, or payment that Infinity already knows.
2. Staff panel and subscriber channel stay distinct: Infinity for operators, Interplus (or the operator’s brand) for the abonado.
3. Legal and money paths are first-class: SIFEN, cobros, cortes, and promesas are not secondary to “CRM.”
4. Network control belongs in the same product as billing: FTTH and MikroTik are part of serving the client, not a separate tool.
5. Permissions define the product: design for the role that is actually looking, not a single admin god-view.
