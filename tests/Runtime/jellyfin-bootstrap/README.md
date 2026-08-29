# Jellyfin Test Bootstrap

The runtime suite must not depend on a developer manually completing Jellyfin's first-run wizard.

The test image/bootstrap process is responsible for producing a disposable Jellyfin instance with:

- startup wizard completed
- one admin user
- one API key exposed to the test runner
- at least two deterministic libraries for library-policy tests
- no pre-existing CAPTAiNFiN service users

The bootstrap data is test-only and must never be reused for production installation guidance.
