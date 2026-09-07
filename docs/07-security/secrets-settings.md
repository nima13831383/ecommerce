# Secrets and Settings

Status: Canonical
Last reviewed: 2026-09-07
Applies to: Current repository

Keep infrastructure/provider secrets in environment/configuration or encrypted secret Site Settings as defined by the registry. Never commit, print, log, render, or place credentials in reports, fixtures, Blade, API Resources, or change records. Use `settings:status`/`settings:sync` without exposing secret values.
