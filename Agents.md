# AGENTS.md

## Permanent Database Safety Rule

Existing database data is valuable and persistent. Never run `php artisan migrate:fresh`, `php artisan db:wipe`, schema drops, truncation, `DROP DATABASE`, `DROP TABLE`, database recreation, or any equivalent destructive database command against the working/development database unless the user explicitly authorizes that exact destructive action in the current task.

Normal migrations must use:

```text
php artisan migrate
```

Automated tests must run against an isolated test database/environment. Never destroy the development database to make a test pass. If a destructive reset appears necessary, stop and report the reason and ask for explicit authorization rather than executing it.

## Project Overview

This repository is a Laravel-based e-commerce platform with an integrated blog.

The project is intended to grow into a large-scale production system, so all implementation decisions must consider:

* scalability
* maintainability
* horizontal scaling
* load balancing
* background processing
* caching
* cache invalidation
* queues
* scheduled tasks
* event-driven architecture
* observability
* data consistency
* failure recovery

Do not treat this project as a small CRUD application.

The current main development priority is the **Admin Panel**, implemented with **Filament**.

The storefront/frontend will be implemented later using **Laravel Blade with SSR**.

Do not spend time implementing or redesigning the public storefront until a frontend design/template is explicitly provided.

---

# Current Development Priority

For now, focus primarily on:

```text
Admin Panel
Domain Architecture
Product Management
Product Types
Variable Products
Orders
Payments
Tax
Inventory
Supporting Commerce Domains
```

Some admin sections already exist, including areas such as:

* products
* taxes
* other commerce-related modules

Existing code must be reviewed before replacing or extending it.

Do not assume existing implementations are correct or production-ready.

Analyze them first and improve them where necessary.

---

# Critical Domain: Products

The product system is one of the most important parts of this project.

The project must support multiple product types.

Among them, **Variable Product** is especially important and must be designed carefully.

Variable product architecture must be capable of supporting concepts such as:

```text
Product
Product Type
Simple Product
Variable Product
Attributes
Attribute Values
Variants
Variant Attribute Combinations
Variant SKU
Variant Price
Variant Sale Price
Variant Stock
Variant Availability
Variant Images
Variant-specific metadata
```

Do not implement variable products as a fragile collection of JSON blobs unless the existing architecture strongly justifies it.

Prefer a relational and extensible domain model where product variants can be queried efficiently.

The final architecture should be able to support large catalogs and a large number of variants.

Pay special attention to:

* variant uniqueness
* attribute combinations
* SKU uniqueness
* stock tracking
* price calculation
* query efficiency
* eager loading
* database indexes
* N+1 queries
* Filament UX
* future storefront usage

Before modifying the product domain, inspect the existing models, migrations, Filament resources, services, relationships, and tests.

---

# Architecture

The project should generally follow:

```text
MVC
SOLID
Event-Driven Architecture
Event Sourcing where justified
Service-oriented domain boundaries
Clear separation of concerns
```

Do not force every feature into Event Sourcing.

Event Sourcing should be used where historical state transitions, auditability, reconstruction, consistency, or business traceability justify the additional complexity.

Strong candidates include:

```text
Orders
Payments
Refunds
Inventory adjustments
Financial state transitions
Critical fulfillment workflows
```

For ordinary CRUD data such as simple content or basic configuration, normal persistence is usually preferable.

---

# Laravel Architecture

Controllers must remain thin.

Business logic should not accumulate inside:

```text
Controllers
Filament Resources
Blade templates
Models
Observers
```

Use appropriate domain/service classes where business rules become non-trivial.

A typical flow may look like:

```text
Request / Filament Action
        ↓
Application / Domain Service
        ↓
Domain Logic
        ↓
Database / Events / Jobs
```

Do not create unnecessary abstractions for trivial operations.

Avoid both extremes:

```text
Fat Controllers / Fat Models
```

and

```text
Overengineered enterprise architecture for simple CRUD
```

Use the simplest architecture that still preserves correctness, scalability, and maintainability.

---

# Event-Driven Architecture

Important domain changes should preferably publish meaningful domain/application events.

Examples:

```text
OrderCreated
OrderConfirmed
OrderCancelled
PaymentInitiated
PaymentSucceeded
PaymentFailed
PaymentRefunded
InventoryReserved
InventoryReleased
InventoryAdjusted
ProductUpdated
ProductPriceChanged
```

Events should represent meaningful business facts.

Do not create events merely to move code between classes.

Listeners should be used for side effects that do not need to execute directly inside the main request flow.

Examples:

```text
notifications
emails
search indexing
cache invalidation
analytics
external synchronization
non-critical projections
audit processing
```

---

# Event Sourcing

Where Event Sourcing is used, the event stream must remain authoritative for the event-sourced aggregate.

Do not implement pseudo-event-sourcing where events are written merely as logs after mutating the main record.

A proper event-sourced flow should conceptually follow:

```text
Command
    ↓
Aggregate
    ↓
Domain Event
    ↓
Event Store
    ↓
Projection / Read Model
```

Important requirements:

* events must be immutable
* events should have stable event types
* event payload compatibility must be considered
* aggregate versioning should be supported where required
* concurrent writes must be handled safely
* projections should be rebuildable
* side effects should not make replay unsafe
* idempotency should be considered

Do not introduce Event Sourcing into a domain until the trade-offs have been analyzed.

---

# Orders

Orders are a critical domain.

Order architecture must account for:

```text
Order items
Pricing snapshots
Taxes
Discounts
Shipping
Payment state
Fulfillment state
Order state transitions
Cancellation
Refunds
Inventory
Audit history
```

Never rely on current Product values to reconstruct historical orders.

Important order information should be snapshotted when the order is created.

For example:

```text
product name
variant
SKU
unit price
discount
tax
quantity
totals
```

Historical orders must remain accurate even if products change later.

---

# Payments

Payment logic must be isolated from controllers and UI.

Payment operations must consider:

```text
idempotency
duplicate callbacks
gateway retries
network failures
race conditions
payment verification
transaction references
refunds
payment status transitions
auditability
```

Never mark an order as paid solely because the browser returned from a gateway.

Payment confirmation must be based on authoritative server-side verification.

Sensitive payment state changes should be transactional.

---

# Database Transactions

Use database transactions around multi-step state changes where partial completion could corrupt business state.

Examples:

```text
creating order + order items
payment confirmation
inventory reservation
inventory deduction
refund processing
critical financial transitions
```

Do not create long transactions containing external network requests.

Prefer:

```text
DB transaction
    ↓
commit
    ↓
dispatch event/job
```

where appropriate.

---

# Queue Architecture

Heavy or non-critical operations should not unnecessarily block HTTP requests.

Use queued jobs for operations such as:

```text
email
notifications
external API synchronization
report generation
image processing
search indexing
large imports
analytics
non-critical projections
```

Jobs must be designed with production behavior in mind.

Consider:

```text
retries
timeouts
backoff
idempotency
duplicate execution
failed jobs
job uniqueness
queue prioritization
```

Never assume a queued job will execute exactly once.

---

# Scheduling

Recurring system operations should use Laravel Scheduler.

Examples may include:

```text
cleanup jobs
expired reservation processing
scheduled publishing
data synchronization
report generation
temporary data cleanup
maintenance tasks
```

Scheduled commands must be safe under multi-server deployments.

Avoid a situation where every application node executes the same critical scheduled task simultaneously.

Use Laravel mechanisms such as:

```text
onOneServer()
withoutOverlapping()
```

where appropriate.

---

# Load Balancing

The application must remain compatible with multiple application servers behind a load balancer.

Do not rely on server-local state for application correctness.

Avoid assumptions such as:

```text
local session state
local cache as authoritative storage
local uploaded files existing on every node
single-server cron behavior
single-server locks
process-local state
```

Shared state should use appropriate centralized infrastructure.

Production architecture should remain compatible with:

```text
multiple Laravel application nodes
central database
shared Redis
shared/object file storage
workers
scheduler coordination
```

Do not introduce unnecessary infrastructure on the local development environment solely because production will be horizontally scaled.

---

# Cache Strategy

Caching is important, but correctness is more important than cache hit rate.

Cache only data that has a clear invalidation strategy.

Before adding cache, answer:

```text
What is cached?
Why is it cached?
What is the cache key?
How long can it be stale?
What invalidates it?
What happens after updates?
What happens under concurrent requests?
```

Prefer targeted cache invalidation over broad:

```text
Cache::flush()
```

unless a global flush is genuinely required.

Potential cache candidates include:

```text
categories
taxonomies
product summaries
configuration
navigation
expensive read queries
computed read models
```

Frequently changing transactional state may not be suitable for aggressive caching.

---

# Cache Invalidation

Cache invalidation must be explicit and centralized.

Whenever cached domain data changes, identify which cache entries become stale.

Prefer:

```text
domain update
    ↓
event
    ↓
cache invalidation listener
```

where it improves separation of concerns.

Do not scatter cache deletion logic randomly throughout controllers and models.

Be careful with wildcard deletion patterns that may become expensive at scale.

---

# Redis

Production may use Redis for:

```text
cache
queues
locks
rate limiting
sessions
distributed coordination
```

Do not make local development unnecessarily dependent on Redis unless the repository is already configured for it.

The code should use Laravel abstractions wherever possible so the backend driver remains configurable.

Avoid direct coupling to a specific Redis implementation when Laravel's cache/queue/lock abstractions are sufficient.

---

# Concurrency and Race Conditions

Critical commerce operations must be designed for concurrency.

Pay special attention to:

```text
inventory
orders
payments
coupons
limited-use discounts
refunds
variant stock
reservation systems
financial records
```

Potential solutions may include:

```text
database transactions
atomic updates
unique indexes
optimistic locking
pessimistic locking
distributed locks
idempotency keys
```

Do not solve concurrency problems only in PHP application memory.

---

# Database Design

Database design must support future scale.

Before adding or modifying tables:

* inspect existing schema
* inspect query patterns
* identify required indexes
* avoid unnecessary duplication
* preserve foreign key integrity where appropriate
* avoid unbounded JSON when relational querying is required
* consider unique constraints
* consider composite indexes

Do not blindly add indexes to every column.

Indexes should match real query patterns.

---

# Eloquent

Avoid:

```text
N+1 queries
unbounded relationship loading
querying inside loops
unnecessary model hydration
loading full models when IDs are enough
```

Use:

```text
eager loading
select()
withCount()
exists()
chunking
cursor/lazy iteration
query builder
```

where appropriate.

Always consider how a query behaves with:

```text
10 records
10,000 records
1,000,000 records
```

---

# Filament Admin Panel

The admin panel is built using Filament.

Use existing Filament conventions in the project before introducing new patterns.

Admin resources should be optimized for:

```text
usability
query efficiency
validation
authorization
large datasets
search
filters
pagination
bulk actions
relationships
```

Do not load massive datasets into Select components.

For large relationships use:

```text
searchable selects
async relationship search
preload only when dataset is small
```

Avoid N+1 queries in:

```text
tables
columns
filters
relationship managers
form options
```

---

# Existing Filament Code

Some Filament resources already exist.

Do not rewrite them automatically.

For each area under review:

1. inspect current implementation
2. understand intended behavior
3. identify correctness issues
4. identify architectural issues
5. identify performance issues
6. identify UX issues
7. identify authorization issues
8. improve incrementally

Preserve working behavior unless a change is intentional.

---

# Authorization

Admin access must use Laravel authorization mechanisms.

Prefer:

```text
Policies
Gates
Filament authorization hooks
```

Do not rely only on hiding buttons in the UI.

Every sensitive server-side action must enforce authorization independently.

---

# Validation

Validation must exist at the application boundary.

Do not depend solely on frontend validation.

Important domain invariants should also be protected inside the domain/application layer where appropriate.

---

# Frontend

The final customer storefront will be implemented as **Laravel Blade SSR** inside this Laravel application. The website language is Persian/Farsi and the storefront must support RTL content and SEO-friendly server-rendered HTML.

The raw template at `D:\uni-shop-project\front` is an existing static HTML/Tailwind/jQuery design source. It is not a separate frontend application, React/Vue app, remote SPA, API-only client, or separately deployed customer application. Do not recreate its design from scratch unless explicitly requested.

## Frontend / Storefront Integration Context

The Laravel backend and the raw template directory are separate codebases, but the final storefront is same-application Laravel SSR. Before implementing any storefront page:

1. Inspect the existing files under `D:\uni-shop-project\front` first.
2. Reuse its RTL/Persian visual language, responsive behavior, components, assets, page structures, header/footer, product cards, account pages, cart/checkout layouts, and blog layouts.
3. Progressively convert the template into Blade layouts, components, partials, and pages; do not replace it with a generic generated UI.
4. Preserve existing frontend behavior and styling unless a redesign is explicitly requested.

The historical backend readiness audit remains documented in `FRONTEND_INTEGRATION_READINESS_2026-09-03.md`. It identified the lack of customer HTTP contracts at that time; it does not change the final Blade architecture defined here.

### Deployment and Authentication

The default storefront deployment is same-application/same-origin Laravel SSR. Use:

* Laravel `web` middleware
* Breeze session authentication
* Laravel CSRF protection
* Blade forms
* server-side web controllers/actions
* redirects, validation bags, and session flash data where appropriate

Do not introduce Sanctum, bearer tokens, SPA authentication, CORS architecture, or cross-origin cookie complexity for the Blade storefront unless the user explicitly changes the deployment architecture. The existing Phase 2A JSON session-auth endpoints may remain available, but Blade pages do not need to call the application's own API over HTTP.

### Existing Storefront API History and Status

Preserve the existing `/api/v1` work and its reports. Phase 1 provides tested public Product catalog and variation-resolution endpoints, and Phase 2A provides tested JSON session authentication endpoints. These APIs may serve future mobile clients, AJAX interactions where justified, external integrations, or other API consumers.

The current preserved contracts are:

* `GET /api/v1/products`
* `GET /api/v1/products/{product:slug}`
* `POST /api/v1/products/{product}/resolve-variation`
* `POST /api/v1/auth/register`
* `POST /api/v1/auth/login`
* `POST /api/v1/auth/logout`
* `GET /api/v1/auth/me`

The API reports and tests remain part of the project history. There are still no customer HTTP contracts for Cart, Coupon mutations, Address book/geography, Shipping quote, Checkout, Payment, customer Orders, Shipment tracking, customer Notifications, or Blog listing/detail unless a later task adds them.

The Blade storefront should not create unnecessary internal HTTP round-trips:

```text
Blade/Web Controller
        -> shared Query / Domain Service
        -> ViewModel / Blade data
```

Do not route normal SSR rendering through `/api/v1` merely to call another controller. Share the underlying authoritative query/service layers between API and Blade adapters. Do not make frontend code depend directly on Laravel models, database tables, or service internals.

### Server Authority Boundary

Blade templates and JavaScript may collect semantic choices and format/display server results, but must never become authoritative for:

* Product price, effective/sale price, or price state
* discounts, Coupon validity, targeting, or usage limits
* tax
* available inventory or reservation state
* shipping amount, package, origin, weight, volume, or parcel classification
* Checkout totals
* Payment amount or verification
* Order status transitions

These values remain in Laravel domain services. Do not duplicate `ProductPriceResolver`, `ProductVariantService` signature logic, `CouponService`, `TaxCalculator`, `InventoryService`, `ShippingCostResolver`, Checkout pricing/fingerprint logic, Payment verification, or Order transition rules in Blade or JavaScript.

### Blade Conversion Strategy

When storefront implementation begins, progressively convert the raw template into structures such as:

* `resources/views/layouts/...`
* Blade components
* partials
* storefront pages
* account pages

Extract repeated visual units such as header, footer, navigation, product card, price display, gallery, breadcrumbs, pagination, forms, and alerts. Avoid both one giant Blade file and over-componentizing trivial one-off markup.

### Web Controllers and View Data

Customer-facing SSR pages should use dedicated web/storefront controllers or invokable actions. Keep controllers thin and reuse existing layers such as `ProductCatalogQuery`, `ProductPriceResolver`, `ProductVariantService`, `CartService`, `CouponService`, `AddressService`, `ShippingCostResolver`, `CheckoutService`, `OrderService`, and `PaymentService`.

When raw models expose too much internal state, pass dedicated query results, DTOs, presenters/view models, or explicitly prepared arrays. Blade must not depend on inventory ledgers, reservations, internal audit structures, provider payloads, or other admin-only state.

### JavaScript and Variable Products

The jQuery/JavaScript from the raw template may provide menus, modals, tabs, quantity UX, gallery switching, dynamic option selection, and progressive enhancement. For interactions requiring server truth, use normal Blade submissions or small AJAX endpoints only where the UX materially benefits. Do not convert the storefront into an SPA.

Blade may render Product attributes and options, and JavaScript may assist selection, but authoritative variation resolution must use the existing `ProductVariantService`/canonical logic. The frontend must not recreate variation signatures. The existing API resolver may remain available, while Blade can call the shared service directly.

### Cart, Addresses, Shipping, and Checkout

Future Blade Cart pages should call `CartService` through Laravel web controllers and use the authenticated Laravel session/user for ownership. Do not create token API architecture merely for the Blade storefront. Guest-cart behavior should be designed for the Blade/session flow when that phase begins.

Address and checkout forms must use `AddressService` and the authoritative Shipping Service geography dataset. Province/city choices may be rendered server-side or loaded through a small JSON/AJAX endpoint for dependent selection; never create a parallel geography dataset or unrestricted location values.

Blade Checkout must call backend services directly and submit semantic choices only. Never trust hidden fields for authoritative prices, taxes, discounts, shipping calculations, package, origin, or inventory; the server must recalculate at submission.

### Payment

No real payment provider has been selected. Keep Payment architecture provider-neutral and do not add a provider until explicitly requested. The provider boundary may later cover initiation, redirect/client instruction, payment state, and server-side verification; the authoritative amount always comes from the Order.

### Implementation Order

Unless the user changes priorities, future storefront work should proceed as Blade SSR integration:

1. Blade storefront foundation, layouts, and assets
2. Home page
3. Product listing/search/filter
4. Product detail and Variable Product selection
5. Authentication/account template integration
6. Cart
7. Address book and geography
8. Coupon and Shipping quote UX
9. Checkout
10. Payment UX after a provider decision
11. Orders and Shipment tracking
12. Blog
13. Full storefront runtime/browser QA

Backend HTTP API phases are not prerequisites for every Blade page. Add API endpoints only when another consumer needs them, AJAX materially benefits UX, or the user explicitly requests an API contract.

### Storefront Contract Testing

Blade implementation requires Laravel Feature tests for routes, SSR responses, authentication, validation, ownership/IDOR, form submissions, and server-authoritative calculations. Add browser-level tests selectively where JavaScript-heavy behavior cannot be proven by normal Laravel tests; do not call component tests browser E2E without a real browser runner.

For any new customer HTTP contract, add runtime tests for applicable success, validation, authentication, ownership/IDOR, pagination, stable enum values, integer Rial serialization, machine-readable dates, error mapping, cross-user access, and replay/idempotency behavior for mutations. Public monetary values must serialize as integer Rial, never authoritative floats. Public timestamps should use consistent machine-readable ISO-8601 values, and public status/type values must use stable machine enum values rather than Persian admin labels.

### Existing Backend Verification Boundary

The following areas have already been verified within their stated scopes and should be reused rather than casually re-audited during ordinary storefront work: core commerce services, MySQL concurrency invariants, Coupon concurrency, Payment concurrency, Checkout idempotency, cancellation/payment race, Shipment concurrency, Notification intent concurrency, Filament Admin Runtime QA, Product media authorization, Product deletion lifecycle, Variable Product generation UI, catalog/admin CRUD, Post admin lifecycle, the Phase 1 Product Catalog API, and the Phase 2A JSON customer authentication boundary.

This context does not authorize implementing storefront pages or missing APIs automatically. Do not add frontend code, customer endpoints, or real providers unless the user explicitly requests that implementation task.

---

# Blog

The project includes a blog.

The blog should remain part of the same Laravel application unless explicitly changed later.

Typical concerns include:

```text
posts
categories
tags
authors
publishing status
scheduled publishing
SEO metadata
slugs
media
```

Do not over-couple blog models with the commerce domain.

---

# SEO

Because the storefront and blog use SSR, backend implementations should preserve future SEO requirements.

Where applicable consider:

```text
stable slugs
canonical URLs
metadata
structured data compatibility
indexable server-rendered pages
```

Do not implement speculative SEO infrastructure unless relevant to the current task.

---

# API and Service Boundaries

Even though the primary frontend uses Blade, business logic must not be coupled directly to Blade pages.

Future API access should remain possible without rewriting core business logic.

Keep domain/application logic reusable from:

```text
Blade
Filament
Controllers
Commands
Jobs
APIs
```

---

# Observability

Important failures must be observable.

Do not silently swallow exceptions.

Use appropriate Laravel logging and failed-job handling.

Critical flows should expose enough context to diagnose failures without logging secrets or sensitive payment data.

For complex workflows consider meaningful structured context such as:

```text
order_id
payment_id
job_id
user_id
event_id
```

where safe and appropriate.

---

# Security

Follow Laravel security conventions.

Important requirements:

* validate input
* authorize actions
* avoid mass-assignment vulnerabilities
* avoid exposing secrets
* avoid logging credentials
* use CSRF protection where applicable
* prevent IDOR
* safely handle file uploads
* sanitize/escape output appropriately
* protect financial/admin operations
* avoid arbitrary file execution

Do not disable framework security mechanisms to simplify implementation.

---

# Money

Do not use floating-point arithmetic for monetary values.

Prefer integer minor/base currency representation.

If the project uses Iranian Rial:

```text
100000 = 100,000 IRR
```

Keep currency handling consistent across:

```text
products
orders
tax
discounts
shipping
payments
refunds
```

Do not silently mix Rial and Toman.

---

# Taxes

Tax calculations should be centralized.

Avoid duplicating tax formulas across:

```text
Product
Cart
Order
Filament
Checkout
Reports
```

Tax calculations should be deterministic and testable.

Historical orders must preserve the tax values applied at purchase time.

---

# Product Pricing

Product pricing should have a clear authoritative path.

For variable products consider:

```text
base product
variant price
sale price
scheduled sale
tax treatment
future promotions
```

Avoid calculating price independently in multiple UI layers.

---

# Inventory

Inventory rules must be centralized.

For variants, inventory should usually be tracked at variant level where appropriate.

Critical stock operations must consider concurrent purchases.

Never implement stock reduction as:

```php
$model->stock = $model->stock - $quantity;
$model->save();
```

without considering race conditions.

---

# Testing

New business logic must be testable.

Prioritize tests for critical domains:

```text
products
variants
pricing
tax
orders
payments
inventory
discounts
event sourcing
```

Use appropriate:

```text
Unit Tests
Feature Tests
Integration Tests
```

Do not test implementation details unnecessarily.

Test business behavior.

---

# Refactoring

Do not perform large unrelated refactors while completing a focused task.

If a larger architectural issue is discovered:

1. document the issue
2. explain the risk
3. determine whether it blocks the current task
4. refactor only if justified

Prefer incremental improvements.

---

# Dependency Policy

Do not install packages simply because they make a small task easier.

Before adding a dependency:

* check if Laravel already provides the capability
* check whether the project already contains an equivalent package
* evaluate maintenance status
* evaluate production implications

Do not upgrade Laravel, Filament, PHP, or other major dependencies unless explicitly requested.

---

# Coding Style

Follow the existing repository conventions.

Prefer:

```text
strict responsibilities
clear naming
small focused methods
dependency injection
framework-native abstractions
typed PHP where appropriate
DTOs/value objects when they improve correctness
```

Avoid unnecessary complexity.

---

# Source of Truth

Before making architectural assumptions, inspect the actual repository.

The repository is the primary source of truth for:

```text
Laravel version
PHP version
Filament version
existing architecture
models
database schema
services
events
jobs
policies
tests
coding conventions
```

Do not assume a fresh Laravel installation.

---

# Initial Repository Audit

Before major development begins, inspect the project and build a practical understanding of the existing codebase.

At minimum inspect:

```text
composer.json
package.json
config/
routes/
app/Models
app/Services
app/Actions
app/Events
app/Listeners
app/Jobs
app/Policies
app/Filament
database/migrations
database/seeders
tests/
```

Only inspect directories that actually exist.

Also identify:

* product architecture
* product types
* variable product implementation
* inventory architecture
* order architecture
* payment architecture
* tax architecture
* existing queues/jobs
* existing scheduled tasks
* current cache usage
* current cache invalidation
* existing events/listeners
* existing event sourcing
* authorization strategy
* Filament architecture
* major technical debt

---

# Audit Before Rewrite

Do not immediately rewrite existing code.

For existing modules classify findings as:

```text
Keep
Improve
Refactor
Replace
Missing
```

Explain why.

A working implementation should not be replaced solely because another pattern is theoretically cleaner.

---

# Performance Review

When reviewing important modules, check for:

```text
N+1 queries
missing indexes
unbounded queries
large Select option loads
query-in-loop
unnecessary eager loading
repeated calculations
inefficient cache use
synchronous heavy operations
race conditions
```

Do not perform premature micro-optimizations.

Fix problems that can materially affect production behavior.

---

# Development Workflow

For every significant requested feature:

1. Read this AGENTS.md.
2. Inspect the relevant existing code.
3. Understand dependencies and business rules.
4. Identify architecture and data-flow impact.
5. Check concurrency implications.
6. Check caching implications.
7. Check queue/event implications.
8. Check authorization/security.
9. Check database/index implications.
10. Implement the minimum coherent solution.
11. Add/update tests.
12. Run relevant tests/static checks where available.
13. Report what changed.

---

# Before Major Changes

Before a major architectural change, provide a concise report covering:

```text
Current implementation
Problems found
Proposed architecture
Files/components affected
Database impact
Backward compatibility
Concurrency implications
Cache implications
Queue/event implications
Migration risk
```

Do not stop for approval for every small implementation detail unless explicitly instructed.

---

# After Changes

After completing a task report:

```text
Created files
Modified files
Database changes
Events added/changed
Jobs added/changed
Cache behavior
Authorization changes
Tests added
Tests executed
Known limitations
Recommended next step
```

---

# Current Project Direction

Current priorities are:

1. Understand and audit the existing project.
2. Improve the overall architecture where necessary.
3. Continue development of the Filament admin panel.
4. Review and improve existing Product and Tax implementations.
5. Establish a robust Product domain.
6. Give special attention to Variable Products.
7. Prepare Orders, Payments, Inventory, and related domains for production-scale behavior.
8. Use Events, Queues, Scheduler, Cache, and Event Sourcing where they provide real architectural value.
9. Preserve horizontal scaling compatibility.
10. Do not develop the final public storefront until its design/template is provided.

The goal is not merely to make features work.

The goal is to build a maintainable, scalable, production-ready e-commerce system.
