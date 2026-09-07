# Shipping

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

Shipping uses one global store origin and an authenticated customer's authoritative Address destination. Modes are calculator, fixed, and free. Calculator services are Post Pishtaz and Vijeh; `ShippingCostResolver` derives package, normalized weight/volume, parcel nature, and amount from cart/domain data. The browser may choose semantic service/payment inputs but never origin, package, weight, or fee. `/shipping-calculator-test` is diagnostic only and is not the storefront quote API.
