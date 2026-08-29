# Fake WHMCS Harness

The harness provides only the WHMCS-owned primitives that CAPTAiNFiN calls directly during tests. It is not intended to emulate WHMCS as a product.

Rules:

- Tests call the real exported module functions where practical.
- The fake layer must remain small and explicit.
- External media APIs are not faked when an inexpensive real container can be used.
- Behaviour that depends on WHMCS rendering, permissions, activation or undocumented internals remains reserved for a licensed runtime pass.
