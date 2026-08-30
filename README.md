# CAPTAiNFiN for WHMCS

Native WHMCS media-service provisioning and lifecycle management for **Jellyfin and Emby** hosting.

This repository is intentionally separate from [`Ricas13/fin-fusion`](https://github.com/Ricas13/fin-fusion). `fin-fusion` is the behavioural reference implementation; this project ports the relevant provisioning, entitlement, policy, reconciliation, activity and integration behaviour into a self-contained WHMCS module.

## Product goals

- Native WHMCS install: no CAPTAiNFiN SaaS, Node service, Docker stack or separate PostgreSQL database.
- Runs entirely on the operator's WHMCS infrastructure and talks directly to their configured services.
- First-class Jellyfin and Emby support behind one provider-neutral lifecycle/recovery model.
- Preserve CAPTAiNFiN's safety properties: idempotent provisioning, durable operation state, reconciliation, drift repair and explicit external-resource cleanup.
- Match the useful media-server management baseline expected from established WHMCS modules while extending it with Jellyseerr, Stremio, Discord, placement, entitlement, activity/inactivity and diagnostics features.
- Let WHMCS remain the owner of customers, products, invoices, payment gateways, renewals and core billing state.

## WHMCS server configuration

CAPTAiNFiN uses normal WHMCS server fields so no separate runtime service is required:

- **Hostname**: Jellyfin or Emby server hostname/full URL. Reverse-proxy base paths are supported.
- **Username**: `jellyfin` or `emby`. Blank is treated as `jellyfin` for compatibility with early CAPTAiNFiN definitions.
- **Password**: provider API key/token. It is never placed in the server URL.
- **Port / Secure**: used when Hostname is not already a full URL.

For Emby, create a dedicated API key in the Emby Server dashboard and put it in the WHMCS Server Password field. CAPTAiNFiN sends it using `X-Emby-Token`.

## Compatibility evidence

CI currently exercises the exported WHMCS lifecycle against disposable real-server containers for:

- Jellyfin 10.11.11
- Jellyfin 12.0 release candidate line
- Emby 4.9.5.0 stable
- Emby 4.10 beta line

The runtime suite covers connection, account creation/idempotency, policy/suspension, unsuspension, package changes, password changes, termination and durable SQL state. Provider versions are pinned in CI so upstream tag movement cannot create false confidence.

## Status

Pre-license hardening is in progress. Core lifecycle/recovery, provider abstraction, policy primitives, diagnostics and runtime compatibility testing are being completed before final validation inside a licensed WHMCS installation.
