# Project Overview

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

Uni Shop is a Laravel e-commerce backend and Blade SSR storefront. Filament provides admin workflows; Laravel web controllers and services provide customer SSR pages; `/api/v1` remains a tested adapter for public catalog, variation lookup, and JSON session authentication consumers.

The system owns products and variable combinations, pricing, tax, inventory, cart, coupons, addresses, shipping, checkout, orders, payments, shipments, notifications, settings, and blog/CMS content. Historical orders use snapshots. Financial, inventory, coupon, and payment state transitions are server-authoritative and transaction-aware.

The raw Persian RTL design source is `D:\uni-shop-project\front`; it is not a separately deployed application. Storefront work converts that source into Blade layouts/components while preserving its visual language.

Current implementation status is documented by code and the phase reports in [`../history/audits/`](../history/audits/). This page is a stable orientation guide, not a replacement for domain documentation.
