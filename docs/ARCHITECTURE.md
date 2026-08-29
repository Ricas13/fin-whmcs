# Architecture

## Product boundary

CAPTAiNFiN for WHMCS is a self-contained WHMCS addon + provisioning module. It is not a hosted CAPTAiNFiN service and must not require infrastructure operated by the module author.

The operator installs the package into WHMCS and configures their own Jellyfin, Jellyseerr, Discord and Stremio-related integrations. All operational state stays in the operator's WHMCS database.

## Ownership

### WHMCS owns

- clients and authentication
- products/services and configurable options
- invoices, renewals, tax and payment gateways
- cancellation requests and service billing status
- affiliate/credit functionality where native WHMCS behaviour is sufficient
- admin roles and permissions

### CAPTAiNFiN owns

- integration configuration and health
- product -> media entitlement policy
- Jellyfin server placement and account binding
- Jellyfin libraries and user policy
- stream/transcode/network policy
- Jellyseerr/request-service provisioning
- Discord managed-role state
- Stremio access/configuration state
- activity observations and inactivity policy inputs
- durable provisioning/deprovisioning operations
- remote/local reconciliation and drift repair
- diagnostics and audit history for module-owned state

## WHMCS surfaces

Two module types are shipped together:

1. `modules/servers/captainfin` - the product/provisioning module. WHMCS calls this synchronously for CreateAccount, SuspendAccount, UnsuspendAccount, TerminateAccount, ChangePackage and other supported lifecycle actions.
2. `modules/addons/captainfin` - admin UI, shared domain code, schema upgrades, health/diagnostics, reconciliation hooks and client-area support.

Shared code lives under the addon module and is loaded by the provisioning module through a small module-local autoloader. Runtime installation must not require Composer on the customer's server.

## Canonical lifecycle

WHMCS service state is not treated as proof that all external systems converged. Every state-changing module action is represented by a durable operation.

Canonical operation states:

- `planned` - desired change is durable locally, remote mutation has not been proven
- `remote_applied` - expected remote change is proven, local binding/state still needs convergence
- `local_applied` - remote and local expected state are both proven
- `failed` - transient/retryable failure with error context
- `manual_attention` - automatic convergence is unsafe or impossible
- `superseded` - newer durable intent for the same WHMCS service made the older operation obsolete

A provider/API timeout after an ambiguous remote call must not cause blind duplicate account creation or destructive retries. Reconciliation observes remote state first and only repeats a mutation when the operation type declares that repeat to be safe.

## Idempotency and ordering

Every lifecycle mutation gets an idempotency key derived from the WHMCS service, operation type and relevant target version/state. Duplicate WHMCS callbacks converge on the same operation rather than creating parallel remote mutations.

Remote resource identifiers are retained independently of the transient request so failed local application can be recovered.

Lifecycle execution is serialized per WHMCS service with a database advisory lock. The retry/reconciliation owner has a separate global advisory lock so overlapping WHMCS cron processes cannot run the same recovery batch concurrently.

For different intents on one service, newer durable intent wins. A stale failed `suspend` must therefore never replay after a newer `unsuspend`, package change or termination. Older unresolved operations are marked `superseded` rather than silently discarded.

## Deletion invariant

Termination is complete only when every enabled external entitlement is either:

- proven removed/disabled; or
- represented by durable cleanup state that still contains enough remote identity to retry after the WHMCS service changes state.

A local row must never be the only copy of a remote identity required for cleanup.

If WHMCS already marks a service Cancelled/Terminated while an older non-termination operation is unresolved, automatic recovery converts that stale intent into the WHMCS `ModuleTerminate` path rather than recreating access.

## Runtime model

### Synchronous path

WHMCS lifecycle calls perform the smallest safe synchronous reconciliation needed to return a truthful result to WHMCS.

### Standard WHMCS cron

`modules/addons/captainfin/hooks.php` attaches the canonical reconciler to `AfterCronJob`.

Automatic recovery:

- only considers stale `planned`/`remote_applied` operations and explicitly retryable `failed` operations
- rechecks the operation immediately before replay
- refuses to invoke a service that no longer belongs to the CAPTAiNFiN provisioning module
- converts ended-service stale intent to cleanup
- does not replay unresolved `create` against a manually Suspended service
- does not automatically replay password changes because the intended secret is deliberately not stored in the durable journal
- stops after a bounded attempt budget and moves unsafe/unrecoverable cases to `manual_attention`
- re-enters lifecycle through WHMCS module commands, preserving WHMCS as the owner of service context

Routine cron passes are silent. WHMCS activity logging is reserved for meaningful recovery/supersession/manual-attention summaries and top-level reconciliation failures.

The same cron surface will also own:

- remote/local drift reconciliation
- health refresh
- cleanup retries
- lower-frequency inactivity and housekeeping work

### Policy sampler

Near-real-time stream/transcode/network enforcement cannot honestly depend on a five-minute billing cron. The product therefore has a dedicated PHP CLI policy sampler that can be scheduled every minute (and may internally sample at a shorter bounded interval). It is still part of the installed WHMCS module: no Node daemon or external control plane is required.

Event-driven Jellyfin ingestion can supplement sampling where available, but enforcement must degrade safely when telemetry for the relevant server is stale.

## Telemetry trust

Activity/inactivity decisions are scoped to the Jellyfin server(s) relevant to the affected service/product. An unrelated offline server must not make the whole fleet either trusted or untrusted.

When required telemetry for a customer's assigned server is stale or a sample failed, destructive inactivity enforcement is skipped for that scope.

## Integration adapters

External systems are accessed through explicit adapters:

- Jellyfin
- Jellyseerr/request service
- Discord
- Stremio

Domain lifecycle code must not call curl directly. Adapters expose observed state and idempotent/safely-repeatable mutations so reconciliation can reason about ambiguity.

## Database

Module-owned tables use the `mod_captainfin_` prefix. The initial schema is intentionally small and will expand through versioned addon upgrades.

Core records:

- servers/integration endpoints
- product policies
- service bindings / remote identities
- durable operations
- activity/policy observations
- audit/diagnostic events

Deactivation does not drop operational tables. Uninstall/removal of data must be a separate explicit destructive action.

## Security

- Secrets must not be exposed in module logs or diagnostic exports.
- Integration credentials will use WHMCS-supported secret storage/encryption rather than plaintext module tables.
- Client-area actions require WHMCS authentication and service ownership checks.
- Remote TLS verification is on by default; insecure TLS must never silently become the fallback.
- State-changing operations record actor/source and correlation information.
- Recovery never invokes another provisioning module after a WHMCS product/module reassignment.

## Release principle

The first public release should not be a thin API wrapper. v1.0 is expected to provide the useful established Jellyfin-WHMCS management baseline plus the CAPTAiNFiN reliability model and cross-service provisioning features.