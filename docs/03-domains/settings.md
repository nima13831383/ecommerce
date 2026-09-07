# Site Settings

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

`SettingRegistry` defines core keys, groups, types, defaults, nullability, options, validation, and secret status. `SettingsService` is the runtime read/write boundary; additive synchronization inserts missing rows without overwriting existing values. Filament edits values only. Infrastructure secrets remain environment/configuration concerns. Payment credentials are encrypted secret settings, masked in admin, and never logged or rendered.
