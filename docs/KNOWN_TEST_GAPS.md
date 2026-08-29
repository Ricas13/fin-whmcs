# Known Test Gaps

The repository deliberately distinguishes implemented harness plumbing from completed runtime evidence.

Current gaps that must be closed before v1.0:

- deterministic unattended Jellyfin first-run/API-key bootstrap in CI
- module-owned DB schema bootstrapping without a licensed WHMCS runtime
- end-to-end exported lifecycle mutation assertions against real Jellyfin
- fault injection for ambiguous HTTP completion and local persistence failures
- licensed-WHMCS mounted runtime suite

These gaps are tracked explicitly so skipped integration tests cannot be mistaken for passing evidence.
