# Users, Authentication, and OTP

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

The storefront uses Laravel Breeze `web` sessions and CSRF for Blade pages. JSON auth remains under `/api/v1` for other consumers. `CustomerOtpService` owns purpose-bound, hashed, expiring OTP challenges, attempts, resend cooldowns, invalidation, and change-mobile state. Provider calls use the SMS adapter; tests use doubles and never live credentials. Soft-deleted users cannot authenticate, and customer responses omit roles, permissions, hashes, and admin metadata.
