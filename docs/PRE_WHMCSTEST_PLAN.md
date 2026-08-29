# Pre-WHMCS Hardening Plan

This document tracks the work that should be completed before spending time on a licensed WHMCS runtime.

## Goals

1. Exercise the actual CAPTAiNFiN module entrypoints in a deterministic local harness.
2. Use real external-service APIs where practical, especially Jellyfin.
3. Finish core media-service behaviour independently of WHMCS rendering quirks.
4. Keep all destructive policy fail-safe under stale or ambiguous telemetry.
5. Make installation, diagnostics, upgrades and supportability first-class product behaviour.

## Delivery slices

- Local WHMCS compatibility shims + real Jellyfin integration harness
- Complete Jellyfin placement, library policy and drift reconciliation
- Operator diagnostics/recovery backend and support bundle
- Jellyseerr adapter and lifecycle convergence
- Discord managed-role adapter and lifecycle convergence
- Stremio entitlement/credential adapter boundary and lifecycle convergence
- Playback activity ingestion, per-server telemetry trust and inactivity policy
- Concurrent stream/transcode/4K/network policy sampler
- Schema upgrade and packaging validation

## Real WHMCS validation reserved for licensed environment

The licensed WHMCS pass remains mandatory for:

- addon activation/upgrades
- Capsule compatibility against the supported WHMCS release
- real `localAPI()` command behaviour
- real cron-hook mounting
- admin/product configuration rendering
- client-area templates and permissions
- final Marketplace package installation

Passing the local harness is not treated as proof of those WHMCS-owned behaviours.
