# Glossary

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

- **Product**: sellable catalog entity; may be simple or variable.
- **Variation**: one canonical combination of a variable Product's attribute options.
- **Cart**: mutable customer/session basket; it does not reserve inventory.
- **Reservation**: time-bounded inventory hold created by checkout/order flows.
- **Snapshot**: immutable order-time copy of customer, product, pricing, tax, or shipping data.
- **Quote**: current server calculation for shipping or checkout preview; it is not an order.
- **StorefrontQueryCache**: centralized public catalog/blog cache adapter with generation and stampede controls.
- **Core setting**: registry-defined persisted configuration value managed through `SettingsService`.
- **Attempt**: persisted payment attempt that binds amount, authority/reference, and verification lifecycle.
- **Public Order number**: customer-safe order identity used in URLs where supported.
