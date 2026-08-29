# Testing Strategy

CAPTAiNFiN uses layered evidence. No single layer is allowed to stand in for another.

## 1. Pure unit tests

Policy, planner, failure-classification and mapping logic without DB/network dependencies.

## 2. Module contract tests

Verify exported WHMCS module surfaces and safety defaults. These tests prove shape, not mounted behaviour.

## 3. Local runtime integration

Exercise real CAPTAiNFiN entrypoints against module-owned SQL tables and real external-service containers. Jellyfin is the first required real adapter target.

## 4. Licensed WHMCS runtime

Required before release for activation/upgrades, real Capsule/localAPI/cron mounting, admin/client rendering, permissions and Marketplace installation.

## Release rule

A state-changing feature is not considered proven solely because source text contains the expected function or because a unit test directly calls an internal class. Important mutations require runtime evidence at the strongest practical layer.
