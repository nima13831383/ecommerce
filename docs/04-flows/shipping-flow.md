# Shipping Quote Flow

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

Owned Address + semantic service/payment choice → `ShippingCostResolver` loads global origin/settings and derives cart weight, volume, parcel nature, and package → fixed/free/calculator mode returns a server quote → Blade renders the quote. Changing Cart, Address, or service requires a fresh calculation. Browser never submits package or fee.
