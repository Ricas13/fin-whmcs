# Commercial editions

CAPTAiNFiN uses one engineering codebase and three commercial editions.

| Edition | SKU | Media-server capability |
| --- | --- | --- |
| CAPTAiNFiN for Jellyfin | `captainfin-jellyfin` | Jellyfin only |
| CAPTAiNFiN for Emby | `captainfin-emby` | Emby only |
| CAPTAiNFiN Media Suite | `captainfin-media-suite` | Jellyfin + Emby |

## Packaging rule

Do not fork the codebase by commercial product. `main`/release branches remain shared.

`scripts/build-edition.php` produces an edition-specific WHMCS ZIP with an embedded `modules/addons/captainfin/edition.json` manifest.

Single-provider packages also omit the other provider's concrete adapter directory:

- Jellyfin edition omits `lib/Integrations/Emby/`.
- Emby edition omits `lib/Integrations/Jellyfin/`.
- Media Suite contains both.

CI must build and validate all three packages on every change affecting runtime packaging.

## Runtime enforcement

The embedded edition manifest is read by `Commercial\Edition` and enforced by `Commercial\EditionGate`.

Connection tests and access-granting/mutating lifecycle operations require the configured media provider to be included in the installed edition.

The following operations are gated:

- create
- unsuspend
- change package
- change password

The following operations intentionally remain available even when the current edition no longer includes that provider:

- suspend
- terminate

This is a safety invariant. A downgrade from Media Suite to a single-provider edition must never prevent CAPTAiNFiN from revoking or deleting access that already exists on the now-unlicensed provider.

## Marketplace/commercial positioning

The intended public catalogue is:

1. **CAPTAiNFiN for Jellyfin** — separate Marketplace/searchable product.
2. **CAPTAiNFiN for Emby** — separate Marketplace/searchable product.
3. **CAPTAiNFiN Media Suite** — bundle/upgrade product enabling both providers.

The products share implementation, lifecycle semantics, reconciliation, diagnostics, policy and integration infrastructure. Provider-specific API mechanics remain isolated behind media-server adapters.

## Licensing boundary

The edition manifest/package isolation implements the product capability model now, but it is **not the final piracy/anti-tamper mechanism** while PHP source remains readable.

Before commercial release, connect `EditionGate` to the chosen licence-verification mechanism (for example an ionCube-protected signed licence or cached online/offline licence state). The commercial licence must resolve to the same three edition IDs/SKUs above rather than creating a second entitlement model.

Do not make remote licence availability a prerequisite for safety cleanup. Suspend/terminate must continue to work during licence-server outages or after entitlement expiry so external access can always be removed.
