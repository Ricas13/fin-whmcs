# Runtime Integration Contract

The pre-WHMCS runtime suite is designed to run in CI with:

- PHP matching the supported module matrix
- MariaDB/MySQL-compatible storage for module-owned tables
- a real Jellyfin container
- deterministic WHMCS-owned fixtures exposed through the minimal harness

It must call the exported CAPTAiNFiN module entrypoints and assert both durable local state and observed Jellyfin state.

The harness does **not** claim to validate WHMCS admin/client rendering, activation, permissions or internal API compatibility. Those remain gated on a licensed WHMCS environment.
