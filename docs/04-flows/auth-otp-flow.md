# Authentication and OTP Flow

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

Breeze handles web session login/register/logout/reset. For mobile OTP, `CustomerOtpService` creates a purpose-bound hashed challenge, applies cooldown/attempt/expiry rules, asks the provider adapter to send, and marks delivery state only after provider success. Verify consumes/invalidate rules safely; change-mobile invalidates the old challenge. Tests use a provider double.
