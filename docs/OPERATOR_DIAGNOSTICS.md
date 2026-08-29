# Operator Diagnostics Contract

The WHMCS UI will render these backend states once a licensed runtime is available:

- integration connectivity/latency without secret disclosure
- operation counts by `planned`, `remote_applied`, `failed`, `manual_attention`, `superseded`
- retry eligibility and next retry time
- service binding / remote identity presence
- relevant-server telemetry freshness
- reconciliation last-run summary
- safe manual retry/reconcile actions
- secret-redacted downloadable support bundle

Manual resolve must never mean silently marking an external cleanup complete; it requires an explicit observed-state or documented operator override audit event.
