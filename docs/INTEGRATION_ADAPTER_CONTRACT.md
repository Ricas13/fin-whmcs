# Integration Adapter Contract

Every external entitlement adapter (Jellyfin, Jellyseerr, Discord, Stremio) must expose observed state and converge toward desired state.

Rules:

- unchanged desired state performs zero remote mutation calls
- ambiguous mutation results are observed before repetition
- remote immutable identity is persisted before local completion is declared
- termination/removal is complete only after expected remote absence/disabled state is observed
- same-name/same-email resources are never adopted or deleted without ownership proof
- adapter HTTP calls use bounded connect/overall deadlines and TLS verification
- diagnostics never emit credentials/tokens
