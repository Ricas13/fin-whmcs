# Current Pre-License Status

Implemented on this branch:

- minimal fake-WHMCS boundary and runtime evidence contract
- disposable MariaDB/Jellyfin compose stack and opt-in real Jellyfin connectivity smoke
- deterministic multi-server placement and explicit migration planning
- all/include/exclude library entitlement + admin override + customer narrowing
- per-server telemetry trust and fail-safe inactivity decisions
- playback start/stop parsing and honest presentation metadata
- stream/transcode/4K/network enforcement planning
- shared observed-state integration contracts and desired-state diffing
- Discord managed-role observed-state adapter
- diagnostics health model, operation backend and support-bundle redaction
- Marketplace package layout/build validation

Still requiring concrete external/runtime work before the licensed WHMCS pass:

- unattended Jellyfin test bootstrap and SQL-mounted full lifecycle integration suite
- concrete Jellyseerr adapter verified against the actual current API/reference implementation
- concrete application-specific Stremio adapter port from fin-fusion
- persistence/executor wiring for activity and policy sampler
- versioned schema expansion for those new operational records
