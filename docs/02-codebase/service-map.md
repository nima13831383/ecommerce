# Service and Model Map

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

| Capability | Primary services/models |
| --- | --- |
| Catalog and price | `ProductCatalogQuery`, `ProductPriceResolver`, Product, ProductVariation, taxonomy models |
| Variable products | `ProductVariantService`, Attribute, AttributeValue, ProductVariation |
| Inventory | `InventoryService`, InventoryTransaction, InventoryReservation |
| Cart | `CartService`, Cart, CartItem |
| Coupons | `CouponService`, Coupon, CouponUsage |
| Customer identity | User, `CustomerOtpService`, Breeze/web auth |
| Addresses | `AddressService`, Address, AddressType |
| Shipping | `ShippingCostResolver`, `PostShippingCalculator`, `WordpressShippingDataLoader`, `ShippingOptionCatalog` |
| Checkout/orders | `CheckoutService`, `OrderService`, Order, OrderItem, OrderStatusHistory |
| Payment | `PaymentService`, Payment, PaymentTransaction, provider gateway contract |
| Fulfillment | `ShipmentService`, Shipment, ShipmentStatusHistory |
| Notifications | notification services/events and `DeliverCustomerNotification` |
| Blog | Post and taxonomy models, public blog controllers/resources |
| Settings | `SettingsService`, `SettingRegistry`, Setting |
| Public cache | `StorefrontQueryCache`, cache generation/rebuild/refresh services and jobs |

Use repository search before assuming a class name is a public extension point. This map is a navigation aid, not permission to bypass a service boundary.
