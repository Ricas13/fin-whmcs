# CAPTAiNFiN for WHMCS

Native WHMCS media-service provisioning and lifecycle management for Jellyfin-based hosting.

This repository is intentionally separate from [`Ricas13/fin-fusion`](https://github.com/Ricas13/fin-fusion). `fin-fusion` is the behavioural reference implementation; this project ports the relevant provisioning, entitlement, policy, reconciliation, activity and integration behaviour into a self-contained WHMCS module.

## Product goals

- Native WHMCS install: no CAPTAiNFiN SaaS, Node service, Docker stack or separate PostgreSQL database.
- Runs entirely on the operator's WHMCS infrastructure and talks directly to their configured services.
- Preserve CAPTAiNFiN's safety properties: idempotent provisioning, durable operation state, reconciliation, drift repair and explicit external-resource cleanup.
- Match the useful Jellyfin-management baseline expected from established WHMCS modules while extending it with Jellyseerr, Stremio, Discord, placement, entitlement, activity/inactivity and diagnostics features.
- Let WHMCS remain the owner of customers, products, invoices, payment gateways, renewals and core billing state.

## Status

Initial architecture/bootstrap in progress. The first milestone is a native WHMCS addon + provisioning-module skeleton with a formal feature-port matrix and testable lifecycle contracts.
