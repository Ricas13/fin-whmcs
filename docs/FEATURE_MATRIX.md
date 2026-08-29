# Feature Port Matrix

This is the initial contract for the WHMCS edition. `fin-fusion` remains the behavioural reference until each relevant capability is explicitly classified and tested here.

Legend:

- **WHMCS** - replace CAPTAiNFiN implementation with native WHMCS ownership/events
- **PORT** - preserve behaviour/invariants in PHP
- **REDESIGN** - preserve product behaviour but adapt the execution model to WHMCS
- **ADD** - established WHMCS/Jellyfin management capability to include even where current CAPTAiNFiN does not expose it identically

## Platform and billing boundary

| Capability | Disposition | Notes |
|---|---|---|
| Customer accounts | WHMCS | WHMCS clients/services are canonical |
| Checkout/order forms | WHMCS | No duplicate checkout |
| Invoices/tax/renewals | WHMCS | No duplicate billing engine |
| Stripe/PayPal/other gateways | WHMCS | Consume WHMCS lifecycle, do not own provider subscriptions |
| Cancellation workflow | WHMCS + PORT | WHMCS triggers lifecycle; module owns external convergence |
| Product/plan catalogue | WHMCS + PORT | WHMCS product is canonical; CAPTAiNFiN policy maps product/configurable options to entitlements |
| Affiliate/account credit | WHMCS by default | Enhanced CAPTAiNFiN accounting only if a real gap is proven |

## Jellyfin provisioning and account lifecycle

| Capability | Disposition |
|---|---|
| Multiple Jellyfin servers | PORT |
| Server classes / plan-to-server eligibility | PORT |
| Automatic placement | PORT |
| Manual server assignment/override | PORT |
| Placement preview/diagnostics | PORT |
| Create account | PORT |
| Suspend/disable account | PORT |
| Unsuspend/enable account | PORT |
| Terminate account | PORT |
| Change package / entitlement reconciliation | PORT |
| Password reset/change | PORT |
| Preserve remote user identity for cleanup/recovery | PORT |
| Drift detection/repair | PORT |
| Bulk/reconciliation jobs | REDESIGN |

## Libraries and user policy

| Capability | Disposition |
|---|---|
| Plan -> library policy | PORT |
| Library assignment | PORT |
| User-selectable libraries | ADD |
| Enforce allowed library subset | PORT |
| Reconcile library drift | PORT |
| Enhanced account information in admin/client area | ADD |
| Re-invite / re-provision user action | ADD |

## Stream, transcode and network policy

| Capability | Disposition |
|---|---|
| Concurrent stream limit | PORT/ADD |
| Terminate sessions above limit | ADD |
| Concurrent transcode limit | PORT/ADD |
| 4K transcode policy / blocking | ADD |
| Multi-IP / household-network policy | PORT/ADD |
| Per-plan stream policy | PORT |
| Per-service/admin overrides | PORT |
| Policy event/audit trail | PORT |
| Safe degradation when telemetry is stale | PORT |
| Near-real-time enforcement | REDESIGN |

## Activity and inactivity

| Capability | Disposition |
|---|---|
| Playback/session ingestion | REDESIGN |
| Start/stop event ingestion where Jellyfin provides it | PORT/REDESIGN |
| Periodic session sampling fallback | REDESIGN |
| Per-server telemetry trust | PORT |
| Recent playback/activity representation | PORT |
| Free-tier inactivity policy | PORT |
| Skip destructive enforcement on stale relevant telemetry | PORT |
| Activity/policy diagnostics | PORT |

## Jellyseerr / request service

| Capability | Disposition |
|---|---|
| Account provisioning/mapping | PORT |
| Permissions/entitlement sync | PORT |
| Desired-state diff before mutation | PORT |
| Bounded bulk reconciliation | REDESIGN |
| Account/permission removal on termination | PORT |
| Durable remote identity for post-delete cleanup | PORT |

## Discord

| Capability | Disposition |
|---|---|
| Managed role mapping | PORT |
| Grant role on entitlement | PORT |
| Remove role on suspension/termination | PORT |
| Await/prove removal during destructive cleanup | PORT |
| Retry failed role reconciliation | PORT |
| Discord identity mapping | PORT |

## Stremio

| Capability | Disposition |
|---|---|
| Stremio access entitlement | PORT |
| Credentials/password management | PORT |
| Customer settings/actions | PORT |
| Suspend/disable semantics | PORT |
| Termination/cleanup | PORT |
| Reconciliation and audit | PORT |

## Reliability and operations

| Capability | Disposition |
|---|---|
| Durable operation journal | PORT |
| planned -> remote_applied -> local_applied lifecycle | PORT |
| Retryable failure state | PORT |
| Manual-attention terminal state | PORT |
| Idempotency / duplicate callback protection | PORT |
| Ambiguous remote-call recovery | PORT |
| External-resource cleanup after local failure | PORT |
| Reconciliation owner | PORT |
| Multi-instance worker collision controls | REDESIGN |
| Integration HTTP deadlines | PORT |
| Health/diagnostics dashboard | PORT |
| Support bundle with secret redaction | ADD |
| Audit history | PORT |

## Notifications

| Capability | Disposition |
|---|---|
| Operational/admin notifications | PORT/WHMCS |
| Customer lifecycle notifications | WHMCS first | Prefer WHMCS email templates/events |
| Policy/inactivity notifications | PORT/WHMCS |
| Notification settings | PORT where module-specific |

## Admin and customer experience

| Capability | Disposition |
|---|---|
| Addon dashboard | PORT |
| Integration setup/test connection | PORT |
| Product policy editor | PORT |
| Service-level diagnostics | PORT |
| Retry/reconcile controls | PORT |
| Client service status | PORT |
| Client password/self-service actions | PORT |
| Client library selection | ADD |
| Clear provisioning failure messages | ADD |

## Explicitly not ported as duplicate systems

The WHMCS edition must not recreate these merely for parity:

- standalone CAPTAiNFiN customer authentication
- standalone checkout
- standalone invoice engine
- standalone Stripe/PayPal subscription ownership
- separate PostgreSQL deployment
- Node/Express runtime

## Audit gate before v1.0

This matrix is a starting contract, not permission to assume the audit is complete. Before v1.0, every relevant `fin-fusion` domain, route, worker, migration and state-changing workflow must be accounted for as one of:

1. replaced by WHMCS,
2. ported,
3. intentionally redesigned with equivalent behaviour, or
4. explicitly excluded with a documented product reason.

No feature is considered ported solely because a similarly named handler exists; lifecycle behaviour needs mounted/runtime or integration coverage.