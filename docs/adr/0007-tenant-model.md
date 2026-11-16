# ADR 0007 — Tenant model

Status: Accepted (2026-11-16). Relates to [ADR 0000](0000-reference-architecture.md).

## Context

The reference is multi-tenant (`TenantId`, `CredentialsProvider::forTenant()`). Every path of this API carries a
`platformId` (format not published). Whether one credential set may act for several platforms is not published (§5).

## Decision

- A tenant is one set of OAuth2 credentials and one `platformId`. Port `PlatformResolver::forTenant(TenantId):
  PlatformId` next to `CredentialsProvider` (in-memory adapter, bundle configuration
  `tenants.<name>.{client_id, client_secret, platform_id}`).
- `PlatformId` is a non-empty string kept verbatim (≤ 64 characters, no `/`), no stricter format until one is published.
- Each submission stores its `PlatformId`, so its history stays readable if the configuration changes.

## Consequences

- Two platforms behind the same credentials are two tenants with the same `client_id`; the token cache is shared
  through the cache key ([ADR 0002](0002-http-client-adaptations.md)).

## Amendment (2026-11-16, lead-accepted, lot 8)

- The bundle configuration is `credentials.<tenant>.{client_id, client_secret, platform_id}`, keyed by tenant id; the
  decision above wrote `tenants.<name>.…`, which is not the key. `tenants` is a plain list of tenant ids for the
  commands that cannot discover them, and defaults to the keys of `credentials`.
