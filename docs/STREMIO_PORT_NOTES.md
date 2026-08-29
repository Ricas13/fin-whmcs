# Stremio Port Notes

Stremio in the WHMCS edition must preserve the existing CAPTAiNFiN access, credentials/password, customer settings, suspension and cleanup semantics. Because this integration is application-specific, its concrete remote adapter should be ported from the actual `fin-fusion` implementation rather than inferred from public Stremio APIs.
