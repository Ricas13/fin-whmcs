# Upgrade Invariants

- activation and every versioned upgrade are idempotent
- upgrades never drop operational/customer binding data
- deactivation never drops module tables
- new nullable columns/backfills are safe on large installations
- secrets are never copied into plaintext diagnostic/audit columns
- a failed upgrade reports a truthful error and can be retried safely
