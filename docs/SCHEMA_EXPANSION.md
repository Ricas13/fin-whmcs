# Operational Schema Expansion

The module-owned schema needs durable records for:

- Jellyfin server placement/health metadata beyond WHMCS's server table
- per-service external integration identities
- playback observations and current active sessions
- per-server poll/telemetry freshness
- policy enforcement events
- integration health checks
- audit/support diagnostics

Schema changes must be delivered through versioned addon upgrades and tested for idempotent activation/upgrade. Existing shipped migration/version identifiers must not be rewritten.
